<?php

namespace App\Services\Samo;

use App\Models\SyncRun;
use App\Services\PayeeRouting;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;
use Throwable;

/**
 * Тегли заявките и услугите им от SAMO в нашата база. Само чете от SAMO.
 * full — всички заявки (първото пускане); иначе — създадените/променените през последните sync_days дни.
 */
class SamoSync
{
    private const BATCH = 500;

    public function __construct(private SamoSource $source) {}

    /** @param  callable(string): void|null  $progress */
    public function run(bool $full = false, ?int $days = null, ?callable $progress = null): SyncRun
    {
        $run = SyncRun::create(['started_at' => now(), 'full' => $full]);
        $since = $full ? null : now()->subDays($days ?? config('restrstat.sync_days'))->format('Y-m-d H:i:s');
        try {
            $after = 0;
            while (true) {
                $claims = $this->source->claims($after, self::BATCH, $since);
                if (! $claims) {
                    break;
                }
                $after = end($claims)['inc'];
                $run->services += $this->batch($claims);
                $run->claims += count($claims);
                $run->save();
                if ($progress) {
                    $progress("до заявка $after: {$run->claims} заявки, {$run->services} услуги");
                }
                if (count($claims) < self::BATCH) {
                    break;
                }
            }
            $this->names();
            (new PayeeRouting)->apply();
        } catch (Throwable $e) {
            $run->error = mb_substr($e->getMessage(), 0, 2000);
            $run->finished_at = now();
            $run->save();
            throw $e;
        }
        $run->finished_at = now();
        $run->save();

        return $run;
    }

    /** @param  list<array<string, mixed>>  $claims */
    private function batch(array $claims): int
    {
        $incs = array_column($claims, 'inc');
        $byClaim = [];
        foreach ($this->source->services($incs) as $s) {
            $byClaim[$s['claim']][] = $s;
        }
        $own = config('restrstat.own_partners');
        $online = config('restrstat.online_partners');
        $now = now();
        $claimRows = [];
        $serviceRows = [];
        foreach ($claims as $c) {
            $services = $byClaim[$c['inc']] ?? [];
            $sale = $net = 0;
            $unconverted = false;
            $main = null;
            foreach ($services as $s) {
                $sale += Cents::from($s['sale']);
                $net += Cents::from($s['net']);
                $unconverted = $unconverted || $s['unconverted'];
                // основната услуга за дестинацията — с хотел и най-голяма продажна цена
                $score = [$s['hotel'] ? 1 : 0, $s['town'] || $s['country'] ? 1 : 0, Cents::from($s['sale'])];
                if ($main === null || $score > $main[0]) {
                    $main = [$score, $s];
                }
                $serviceRows[] = [
                    'samo_inc' => $s['inc'], 'claim' => $c['inc'], 'supplier' => $s['supplier'], 'payee' => $s['supplier'],
                    'country' => $s['country'], 'town' => $s['town'], 'hotel' => $s['hotel'],
                    'date_from' => $s['date_from'] ?? $c['date_from'], 'date_to' => $s['date_to'] ?? $c['date_to'],
                    'status' => $s['status'], 'sale' => Cents::str(Cents::from($s['sale'])), 'net' => Cents::str(Cents::from($s['net'])),
                    'net_currency' => $s['net_currency'], 'net_original' => $s['net_original'], 'unconverted' => $s['unconverted'],
                ];
            }
            $channel = in_array($c['partner'], $online, true) ? 'online' : (in_array($c['partner'], $own, true) ? 'own' : 'agency');
            $pct = $c['commission_pct'] !== null ? (float) $c['commission_pct'] : 0.0;
            $commission = $channel === 'agency' && $pct > 0 ? (int) round($sale * $pct / 100) : 0;
            $created = CarbonImmutable::parse($c['created_at']);
            $from = $c['date_from'] ? CarbonImmutable::parse($c['date_from']) : null;
            $to = $c['date_to'] ? CarbonImmutable::parse($c['date_to']) : null;
            $cancelledOn = $c['cancelled_on'] ? CarbonImmutable::parse($c['cancelled_on']) : null;
            $cancelled = in_array($c['status'], [3, 4, 5], true);
            $m = $main[1] ?? [];
            $claimRows[] = [
                'samo_inc' => $c['inc'], 'partner' => $c['partner'], 'channel' => $channel,
                'created_at' => $created->format('Y-m-d H:i:s'), 'created_month' => $created->format('Y-m'),
                'date_from' => $from?->toDateString(), 'date_to' => $to?->toDateString(), 'from_month' => $from?->format('Y-m'),
                'nights' => $c['nights'] ?? ($from && $to ? (int) $from->diffInDays($to) : null),
                'lead_days' => $from ? (int) $created->startOfDay()->diffInDays($from, false) : null,
                'status' => $c['status'], 'cancelled' => $cancelled, 'cancelled_on' => $cancelledOn?->toDateString(),
                'cancel_lead_days' => $cancelled && $cancelledOn && $from ? (int) $cancelledOn->diffInDays($from, false) : null,
                'pax' => $c['pax'],
                'country' => $m['country'] ?? ($c['country'] ?? null), 'town' => $m['town'] ?? null, 'hotel' => $m['hotel'] ?? null,
                'sale' => Cents::str($sale), 'net' => Cents::str($net), 'commission' => Cents::str($commission),
                'margin' => Cents::str($sale - $net - $commission), 'commission_pct' => $c['commission_pct'],
                'unconverted' => $unconverted, 'changed_at' => $c['changed_at'], 'synced_at' => $now,
            ];
        }

        DB::transaction(function () use ($incs, $claimRows, $serviceRows) {
            DB::table('claims')->upsert($claimRows, ['samo_inc']);
            // услугите на тези заявки се подменят изцяло (изтритите в SAMO изчезват)
            DB::table('services')->whereIn('claim', $incs)->delete();
            foreach (array_chunk($serviceRows, 500) as $chunk) {
                DB::table('services')->insert($chunk);
            }
        });

        return count($serviceRows);
    }

    /** Имената на партньорите и местата, които липсват или са стари. */
    private function names(): void
    {
        $partners = DB::table('claims')->whereNotNull('partner')->distinct()->pluck('partner')
            ->merge(DB::table('services')->whereNotNull('supplier')->distinct()->pluck('supplier'))
            ->unique()->values()->all();
        $suppliers = DB::table('services')->whereNotNull('supplier')->distinct()->pluck('supplier')->flip();

        $countries = [];
        foreach (array_chunk($partners, 500) as $chunk) {
            $rows = [];
            foreach ($this->source->partners($chunk) as $p) {
                $rows[] = ['samo_inc' => $p['inc'], 'name' => $p['name'], 'country' => $p['country'],
                    'is_supplier' => isset($suppliers[$p['inc']]), 'synced_at' => now()];
                if ($p['country']) {
                    $countries[] = $p['country'];
                }
            }
            DB::table('partners')->upsert($rows, ['samo_inc'], ['name', 'country', 'is_supplier', 'synced_at']);
        }

        // места: хотели → градовете им → държавите им
        $hotels = $this->distinct('hotel');
        $this->savePlaces('hotel', $hotels);
        $towns = array_merge($this->distinct('town'), DB::table('places')->where('kind', 'hotel')->whereNotNull('parent')->pluck('parent')->all());
        $this->savePlaces('town', $towns);
        $this->savePlaces('country', array_merge($this->distinct('country'), $countries,
            DB::table('places')->where('kind', 'town')->whereNotNull('parent')->pluck('parent')->all()));

        // заявки без държава/град, но с хотел — от картата на хотела
        $this->fillFromParents();

        // турските доставчици по държавата им (ръчно избраните не се пипат)
        $turkey = DB::table('places')->where('kind', 'country')->get()
            ->filter(fn ($p) => $this->isTurkey($p->name))->pluck('samo_inc')->all();
        if ($turkey) {
            DB::table('partners')->where('turkish_manual', false)->update(['is_turkish' => false]);
            DB::table('partners')->where('turkish_manual', false)->whereIn('country', $turkey)->update(['is_turkish' => true]);
        }
    }

    /** @return list<int> */
    private function distinct(string $column): array
    {
        return DB::table('services')->whereNotNull($column)->distinct()->pluck($column)
            ->merge(DB::table('claims')->whereNotNull($column)->distinct()->pluck($column))
            ->unique()->values()->all();
    }

    /** @param  list<int>  $incs */
    private function savePlaces(string $kind, array $incs): void
    {
        foreach (array_chunk(array_values(array_unique($incs)), 500) as $chunk) {
            $rows = array_map(fn ($p) => ['kind' => $kind, 'samo_inc' => $p['inc'], 'name' => $p['name'], 'parent' => $p['parent']],
                $this->source->places($kind, $chunk));
            if ($rows) {
                DB::table('places')->upsert($rows, ['kind', 'samo_inc'], ['name', 'parent']);
            }
        }
    }

    private function fillFromParents(): void
    {
        // по една заявка за хотел/град (не за ред)
        $hotelTown = DB::table('places')->where('kind', 'hotel')->whereNotNull('parent')->pluck('parent', 'samo_inc');
        $townCountry = DB::table('places')->where('kind', 'town')->whereNotNull('parent')->pluck('parent', 'samo_inc');
        foreach (['claims', 'services'] as $table) {
            $hotels = DB::table($table)->whereNotNull('hotel')->whereNull('town')->distinct()->pluck('hotel');
            foreach ($hotels as $h) {
                if (isset($hotelTown[$h])) {
                    DB::table($table)->where('hotel', $h)->whereNull('town')->update(['town' => $hotelTown[$h]]);
                }
            }
            $towns = DB::table($table)->whereNotNull('town')->whereNull('country')->distinct()->pluck('town');
            foreach ($towns as $t) {
                if (isset($townCountry[$t])) {
                    DB::table($table)->where('town', $t)->whereNull('country')->update(['country' => $townCountry[$t]]);
                }
            }
        }
    }

    private function isTurkey(?string $name): bool
    {
        $name = mb_strtolower((string) $name);
        foreach (config('restrstat.turkey_names') as $t) {
            if ($name !== '' && str_contains($name, $t)) {
                return true;
            }
        }

        return false;
    }
}
