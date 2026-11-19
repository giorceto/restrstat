<?php

namespace App\Services;

use Carbon\CarbonImmutable;
use Illuminate\Database\Query\Builder;
use Illuminate\Support\Facades\DB;

/** Статистиките — само от нашата база (claims, services, places, partners). */
class Statistics
{
    /** Срок от продажбата до тръгването (дни). */
    public const LEAD = [[null, -1, 'след тръгване'], [0, 7, 'до 7 дни'], [8, 30, '8–30 дни'], [31, 60, '31–60 дни'],
        [61, 120, '61–120 дни'], [121, 180, '121–180 дни'], [181, null, 'над 180 дни']];

    public const MONTHS = ['', 'яну', 'фев', 'мар', 'апр', 'май', 'юни', 'юли', 'авг', 'сеп', 'окт', 'ное', 'дек'];

    private function base(Filters $f): Builder
    {
        return $f->apply(DB::table('claims'));
    }

    /** Активните (без анулираните). */
    private function active(Filters $f): Builder
    {
        return $this->base($f)->where('claims.cancelled', false);
    }

    private const SUMS = 'COUNT(*) AS claims, COALESCE(SUM(pax), 0) AS pax, COALESCE(SUM(nights), 0) AS nights_sum,
        COUNT(nights) AS nights_cnt, COALESCE(SUM(sale), 0) AS sale, COALESCE(SUM(net), 0) AS net,
        COALESCE(SUM(margin), 0) AS margin, COALESCE(SUM(unconverted), 0) AS unconverted';

    /** @return array<string, float|int|null> */
    public function totals(Filters $f): array
    {
        $a = $this->active($f)->selectRaw(self::SUMS)->first();
        $all = $this->base($f)->count();

        return self::metrics($a) + ['all' => $all, 'cancelled' => $all - (int) $a->claims,
            'cancel_pct' => $all ? round(100 * ($all - (int) $a->claims) / $all, 1) : null];
    }

    /** @return array<string, float|int|null> */
    public static function metrics(object $r): array
    {
        $claims = (int) $r->claims;
        $sale = (float) $r->sale;

        return [
            'claims' => $claims, 'pax' => (int) $r->pax, 'sale' => $sale, 'net' => (float) $r->net, 'margin' => (float) $r->margin,
            'margin_pct' => $sale > 0 ? round(100 * (float) $r->margin / $sale, 1) : null,
            'avg_sale' => $claims ? round($sale / $claims, 2) : null,
            'avg_nights' => (int) ($r->nights_cnt ?? 0) ? round((float) $r->nights_sum / (int) $r->nights_cnt, 1) : null,
            'unconverted' => (int) ($r->unconverted ?? 0),
        ];
    }

    /**
     * По държави, курорти или хотели; с миналата година за сравнение.
     *
     * @return list<array<string, mixed>>
     */
    public function byPlace(Filters $f, string $level, ?int $country = null, ?int $town = null): array
    {
        $rows = function (Filters $f) use ($level, $country, $town) {
            $q = $this->active($f);
            if ($country !== null) {
                $q->where('country', $country);
            }
            if ($town !== null) {
                $q->where('town', $town);
            }

            return $q->selectRaw("$level AS place, ".self::SUMS)->groupBy($level)->get()->keyBy(fn ($r) => (string) $r->place);
        };
        $now = $rows($f);
        $ly = $rows($f->lastYear());
        $names = $this->names($level, $now->keys()->filter(fn ($k) => $k !== '')->all());
        $out = [];
        foreach ($now as $key => $r) {
            $m = self::metrics($r);
            $prev = isset($ly[$key]) ? (float) $ly[$key]->sale : 0.0;
            $out[] = $m + [
                'id' => $key === '' ? null : (int) $key,
                'name' => $key === '' ? '— не е посочено' : ($names[(int) $key] ?? "№ $key"),
                'sale_ly' => $prev, 'claims_ly' => isset($ly[$key]) ? (int) $ly[$key]->claims : 0,
                'change_pct' => $prev > 0 ? round(100 * ($m['sale'] - $prev) / $prev, 1) : null,
            ];
        }
        usort($out, fn ($a, $b) => $b['sale'] <=> $a['sale']);

        return $out;
    }

    /** @return array<int, string> */
    public function names(string $kind, array $ids): array
    {
        if (! $ids) {
            return [];
        }

        return DB::table('places')->where('kind', $kind)->whereIn('samo_inc', $ids)->pluck('name', 'samo_inc')
            ->map(fn ($n, $k) => $n ?? "№ $k")->all();
    }

    /**
     * По месеци (на продажба или на настаняване) — тази и миналата година.
     *
     * @return list<array<string, mixed>>
     */
    public function byMonth(Filters $f): array
    {
        $col = $f->monthColumn();
        $get = fn (Filters $f) => $this->active($f)->selectRaw("$col AS month, ".self::SUMS)->groupBy($col)->get()->keyBy('month');
        $now = $get($f);
        $ly = $get($f->lastYear());
        $out = [];
        for ($m = $f->from->startOfMonth(); $m <= $f->to; $m = $m->addMonth()) {
            $key = $m->format('Y-m');
            $prevKey = $m->subYear()->format('Y-m');
            $cur = isset($now[$key]) ? self::metrics($now[$key]) : self::metrics((object) ['claims' => 0, 'pax' => 0, 'sale' => 0, 'net' => 0, 'margin' => 0]);
            $prev = isset($ly[$prevKey]) ? (float) $ly[$prevKey]->sale : 0.0;
            $out[] = $cur + ['month' => $key, 'label' => self::MONTHS[(int) $m->format('n')].' '.$m->format('Y'),
                'sale_ly' => $prev, 'claims_ly' => isset($ly[$prevKey]) ? (int) $ly[$prevKey]->claims : 0,
                'change_pct' => $prev > 0 ? round(100 * ($cur['sale'] - $prev) / $prev, 1) : null];
        }

        return $out;
    }

    private static function bucketSql(string $col): string
    {
        $cases = [];
        foreach (self::LEAD as $i => [$min, $max]) {
            $cond = [];
            if ($min !== null) {
                $cond[] = "$col >= $min";
            }
            if ($max !== null) {
                $cond[] = "$col <= $max";
            }
            $cases[] = 'WHEN '.implode(' AND ', $cond)." THEN $i";
        }

        return 'CASE '.implode(' ', $cases).' END';
    }

    /**
     * Кога се купува: колко дни преди тръгването.
     *
     * @return list<array<string, mixed>>
     */
    public function leadTime(Filters $f): array
    {
        $rows = $this->active($f)->whereNotNull('lead_days')
            ->selectRaw(self::bucketSql('lead_days').' AS bucket, '.self::SUMS)->groupBy('bucket')->get()->keyBy('bucket');
        $total = $rows->sum('claims');
        $out = [];
        foreach (self::LEAD as $i => [, , $label]) {
            $m = isset($rows[$i]) ? self::metrics($rows[$i]) : self::metrics((object) ['claims' => 0, 'pax' => 0, 'sale' => 0, 'net' => 0, 'margin' => 0]);
            $out[] = $m + ['label' => $label, 'share' => $total ? round(100 * $m['claims'] / $total, 1) : null];
        }

        return $out;
    }

    public function avgLead(Filters $f): ?float
    {
        $v = $this->active($f)->whereNotNull('lead_days')->where('lead_days', '>=', 0)->avg('lead_days');

        return $v === null ? null : round((float) $v, 0);
    }

    /**
     * Сезонът към днешна дата: пътувания през $year, продадени до днешния ден (същия ден преди година за миналата).
     *
     * @return array{year: int, cut: string, now: array, ly: array, months: list<array>}
     */
    public function seasonToDate(int $year, string $channel = 'all'): array
    {
        // продадени до днес; за миналата година — до същия ден преди година
        $cut = CarbonImmutable::today();
        $get = function (int $y, CarbonImmutable $cut) use ($channel) {
            $q = DB::table('claims')->where('cancelled', false)
                ->where('date_from', '>=', "$y-01-01")->where('date_from', '<=', "$y-12-31")
                ->where('created_at', '<=', $cut->format('Y-m-d 23:59:59'));
            if ($channel !== 'all') {
                $q->where('channel', $channel);
            }

            return [
                'total' => self::metrics((clone $q)->selectRaw(self::SUMS)->first()),
                'months' => (clone $q)->selectRaw('from_month AS month, '.self::SUMS)->groupBy('from_month')->get()->keyBy('month'),
            ];
        };
        $now = $get($year, $cut);
        $ly = $get($year - 1, $cut->subYear());
        $months = [];
        for ($m = 1; $m <= 12; $m++) {
            $k = sprintf('%d-%02d', $year, $m);
            $pk = sprintf('%d-%02d', $year - 1, $m);
            $s = isset($now['months'][$k]) ? (float) $now['months'][$k]->sale : 0.0;
            $p = isset($ly['months'][$pk]) ? (float) $ly['months'][$pk]->sale : 0.0;
            $months[] = ['label' => self::MONTHS[$m], 'sale' => $s, 'sale_ly' => $p,
                'claims' => isset($now['months'][$k]) ? (int) $now['months'][$k]->claims : 0,
                'claims_ly' => isset($ly['months'][$pk]) ? (int) $ly['months'][$pk]->claims : 0,
                'pax' => isset($now['months'][$k]) ? (int) $now['months'][$k]->pax : 0,
                'pax_ly' => isset($ly['months'][$pk]) ? (int) $ly['months'][$pk]->pax : 0,
                'change_pct' => $p > 0 ? round(100 * ($s - $p) / $p, 1) : null];
        }

        return ['year' => $year, 'cut' => $cut->toDateString(), 'now' => $now['total'], 'ly' => $ly['total'], 'months' => $months];
    }

    /**
     * Натовареност: колко туристи са на почивка през всяка седмица (по курорт, ако е избран).
     *
     * @return list<array{week: string, label: string, pax: int, claims: int}>
     */
    public function occupancy(CarbonImmutable $from, CarbonImmutable $to, string $channel = 'all', ?int $country = null, ?int $town = null): array
    {
        $q = DB::table('claims')->where('cancelled', false)->whereNotNull('date_from')
            ->where('date_from', '<=', $to->toDateString())->where('date_to', '>=', $from->toDateString());
        if ($channel !== 'all') {
            $q->where('channel', $channel);
        }
        if ($country !== null) {
            $q->where('country', $country);
        }
        if ($town !== null) {
            $q->where('town', $town);
        }
        $claims = $q->get(['date_from', 'date_to', 'pax']);
        $weeks = [];
        for ($w = $from->startOfWeek(); $w <= $to; $w = $w->addWeek()) {
            $weeks[$w->toDateString()] = ['week' => $w->toDateString(), 'label' => $w->format('d.m'), 'pax' => 0, 'claims' => 0];
        }
        foreach ($claims as $c) {
            $start = CarbonImmutable::parse($c->date_from)->startOfWeek();
            $end = CarbonImmutable::parse($c->date_to ?? $c->date_from);
            for ($w = $start; $w <= $end; $w = $w->addWeek()) {
                $k = $w->toDateString();
                if (isset($weeks[$k])) {
                    $weeks[$k]['pax'] += max(1, (int) $c->pax);
                    $weeks[$k]['claims']++;
                }
            }
        }

        return array_values($weeks);
    }

    /**
     * Анулации — дял по група (държава, канал или срок преди тръгване).
     *
     * @return list<array<string, mixed>>
     */
    public function cancellations(Filters $f, string $by): array
    {
        $group = match ($by) {
            'country' => 'country',
            'channel' => 'channel',
            'lead' => self::bucketSql('cancel_lead_days'),
        };
        $rows = $this->base($f)->selectRaw("$group AS g, COUNT(*) AS all_cnt, SUM(CASE WHEN cancelled THEN 1 ELSE 0 END) AS cancelled,
                COALESCE(SUM(CASE WHEN cancelled THEN sale ELSE 0 END), 0) AS cancelled_sale")
            ->when($by === 'lead', fn ($q) => $q->where('cancelled', true))
            ->groupByRaw($group)->get();
        $names = $by === 'country' ? $this->names('country', $rows->pluck('g')->filter()->all()) : [];
        $total = (int) $rows->sum('cancelled');
        $out = [];
        foreach ($rows as $r) {
            $label = match ($by) {
                'country' => $r->g === null ? '— не е посочено' : ($names[(int) $r->g] ?? "№ $r->g"),
                'channel' => Filters::CHANNELS[$r->g] ?? $r->g,
                'lead' => $r->g === null ? 'без дата' : str_replace('след тръгване', 'след/в деня на тръгване', self::LEAD[(int) $r->g][2]),
            };
            $out[] = ['label' => $label, 'order' => $by === 'lead' ? ($r->g ?? 99) : -(int) $r->cancelled,
                'all' => (int) $r->all_cnt, 'cancelled' => (int) $r->cancelled, 'cancelled_sale' => (float) $r->cancelled_sale,
                'pct' => $by === 'lead' ? ($total ? round(100 * (int) $r->cancelled / $total, 1) : null)
                    : ((int) $r->all_cnt ? round(100 * (int) $r->cancelled / (int) $r->all_cnt, 1) : null)];
        }
        usort($out, fn ($a, $b) => $a['order'] <=> $b['order']);

        return $out;
    }

    /**
     * Задължения към доставчик: Σ нетни цени на услугите му в активни заявки.
     *
     * @return array{total: float, unconverted: int, services: int, claims: int, rows: list<object>}
     */
    public function obligations(int $supplier, ?CarbonImmutable $soldFrom, ?CarbonImmutable $soldTo, ?CarbonImmutable $inFrom, ?CarbonImmutable $inTo): array
    {
        $q = DB::table('services as s')->join('claims as c', 'c.samo_inc', '=', 's.claim')
            ->whereRaw('COALESCE(s.payee, s.supplier) = ?', [$supplier])->where('c.cancelled', false);
        if ($soldFrom) {
            $q->where('c.created_at', '>=', $soldFrom->format('Y-m-d 00:00:00'));
        }
        if ($soldTo) {
            $q->where('c.created_at', '<=', $soldTo->format('Y-m-d 23:59:59'));
        }
        if ($inFrom) {
            $q->where('s.date_from', '>=', $inFrom->toDateString());
        }
        if ($inTo) {
            $q->where('s.date_from', '<=', $inTo->toDateString());
        }
        $rows = $q->orderBy('s.date_from')->orderBy('c.samo_inc')
            ->get(['c.samo_inc as claim', 'c.created_at', 's.date_from', 's.date_to', 'c.pax', 's.hotel', 's.town',
                's.net', 's.net_currency', 's.net_original', 's.unconverted', 's.supplier']);
        $viaNames = DB::table('partners')->whereIn('samo_inc', $rows->where('supplier', '!=', $supplier)->pluck('supplier')->unique()->all())
            ->pluck('name', 'samo_inc');
        $hotels = $this->names('hotel', $rows->pluck('hotel')->filter()->unique()->all());
        $towns = $this->names('town', $rows->pluck('town')->filter()->unique()->all());
        // по заявка: един ред със сумата на услугите от доставчика
        $claims = [];
        foreach ($rows as $r) {
            $c = $claims[$r->claim] ??= (object) ['claim' => $r->claim, 'created_at' => $r->created_at, 'date_from' => $r->date_from,
                'date_to' => $r->date_to, 'pax' => $r->pax, 'place' => null, 'services' => 0, 'net' => 0, 'unconverted' => 0, 'other' => [], 'via' => []];
            $c->services++;
            // услуга на друг доставчик, пренасочена тук (напр. трансфер към доставчика на хотела)
            if ((int) $r->supplier !== $supplier && ! $r->unconverted) {
                $name = $viaNames[$r->supplier] ?? '№ '.$r->supplier;
                $c->via[$name] = ($c->via[$name] ?? 0) + (float) $r->net;
            }
            $from = array_filter([$c->date_from, $r->date_from]);
            $to = array_filter([$c->date_to, $r->date_to]);
            $c->date_from = $from ? min($from) : null;
            $c->date_to = $to ? max($to) : null;
            $c->place ??= $hotels[$r->hotel] ?? null;
            $c->town ??= $towns[$r->town] ?? null;
            if ($r->unconverted) {
                $c->unconverted++;
                $c->other[] = number_format((float) $r->net_original, 2, ',', ' ').' '.$r->net_currency;
            } else {
                $c->net += (float) $r->net;
            }
        }
        foreach ($claims as $c) {
            $c->place ??= $c->town;
            $c->net = round($c->net, 2);
        }

        return [
            'total' => round(array_sum(array_map(fn ($c) => $c->net, $claims)), 2),
            'unconverted' => $rows->where('unconverted', true)->count(),
            'services' => $rows->count(),
            'claims' => count($claims),
            'rows' => array_values($claims),
        ];
    }

    /**
     * Салдо по доставчик, без филтрите по период: всичко дължимо (активни заявки, с настаняване от $from), от него — с настаняване до днес,
     * платено (въведените плащания) и остатък.
     *
     * @param  list<int>  $suppliers
     * @return array<int, array{owed: float, owed_to_date: float, paid: float, balance: float, due_now: float, last_paid: ?string}>
     */
    public function balances(array $suppliers, ?CarbonImmutable $from = null): array
    {
        if (! $suppliers) {
            return [];
        }
        $today = CarbonImmutable::today()->toDateString();
        $owed = DB::table('services as s')->join('claims as c', 'c.samo_inc', '=', 's.claim')
            ->whereIn(DB::raw('COALESCE(s.payee, s.supplier)'), $suppliers)->where('c.cancelled', false)->where('s.unconverted', false)
            // салдото се води от началната дата (по-старото е платено преди да въвеждаме плащания тук)
            ->when($from, fn ($q) => $q->where('s.date_from', '>=', $from->toDateString()))
            ->selectRaw('COALESCE(s.payee, s.supplier) AS supplier, COALESCE(SUM(s.net), 0) AS owed,
                COALESCE(SUM(CASE WHEN s.date_from <= ? THEN s.net ELSE 0 END), 0) AS owed_to_date', [$today])
            ->groupByRaw('COALESCE(s.payee, s.supplier)')->get()->keyBy('supplier');
        $paid = DB::table('supplier_payments')->whereIn('supplier', $suppliers)
            ->selectRaw('supplier, COALESCE(SUM(amount), 0) AS paid, MAX(paid_on) AS last_paid')
            ->groupBy('supplier')->get()->keyBy('supplier');
        $out = [];
        foreach ($suppliers as $id) {
            $o = (float) ($owed[$id]->owed ?? 0);
            $t = (float) ($owed[$id]->owed_to_date ?? 0);
            $p = (float) ($paid[$id]->paid ?? 0);
            $out[$id] = ['owed' => round($o, 2), 'owed_to_date' => round($t, 2), 'paid' => round($p, 2),
                'balance' => round($o - $p, 2), 'due_now' => round($t - $p, 2), 'last_paid' => $paid[$id]->last_paid ?? null];
        }

        return $out;
    }
}
