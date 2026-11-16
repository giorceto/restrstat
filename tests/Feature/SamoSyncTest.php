<?php

namespace Tests\Feature;

use App\Services\Samo\SamoSource;
use App\Services\Samo\SamoSync;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class FakeSamo implements SamoSource
{
    public array $claims = [];

    public array $services = [];

    public function claims(int $afterInc, int $limit, ?string $since = null): array
    {
        return array_slice(array_values(array_filter($this->claims, fn ($c) => $c['inc'] > $afterInc)), 0, $limit);
    }

    public function services(array $claims): array
    {
        return array_values(array_filter($this->services, fn ($s) => in_array($s['claim'], $claims, true)));
    }

    public function partners(array $incs): array
    {
        $all = [1 => ['TC', 100], 700 => ['Агенция', 100], 901 => ['TR DMC', 2], 903 => ['GR DMC', 1]];

        return array_values(array_map(fn ($i) => ['inc' => $i, 'name' => $all[$i][0] ?? null, 'country' => $all[$i][1] ?? null],
            array_filter($incs, fn ($i) => isset($all[$i]))));
    }

    public function places(string $kind, array $incs): array
    {
        $all = ['country' => [1 => ['Гърция', null], 2 => ['Турция', null], 100 => ['България', null]],
            'town' => [21 => ['Анталия', 2], 11 => ['Тасос', 1]], 'hotel' => [501 => ['Хотел А', 21]]][$kind];

        return array_values(array_map(fn ($i) => ['inc' => $i, 'name' => $all[$i][0], 'parent' => $all[$i][1]],
            array_filter($incs, fn ($i) => isset($all[$i]))));
    }
}

class SamoSyncTest extends TestCase
{
    use RefreshDatabase;

    private function claim(int $inc, array $o = []): array
    {
        return $o + ['inc' => $inc, 'partner' => 1, 'author' => 5, 'created_at' => '2026-03-01 10:00:00', 'date_from' => '2026-07-01',
            'date_to' => '2026-07-08', 'nights' => 7, 'status' => 2, 'cancelled_on' => null, 'commission_pct' => null, 'changed_at' => null, 'pax' => 2];
    }

    private function service(int $inc, int $claim, array $o = []): array
    {
        return $o + ['inc' => $inc, 'claim' => $claim, 'supplier' => 901, 'country' => null, 'town' => null, 'hotel' => 501,
            'date_from' => null, 'date_to' => null, 'status' => null, 'sale' => '1000.00', 'net' => '850.00', 'net_currency' => '€',
            'net_original' => '850.00', 'unconverted' => false];
    }

    public function test_sync_sums_margin_channels_places_and_turkish_suppliers(): void
    {
        $fake = new FakeSamo;
        $fake->claims = [
            $this->claim(10),
            $this->claim(11, ['partner' => 700, 'commission_pct' => '8']),
            $this->claim(12, ['status' => 3, 'cancelled_on' => '2026-06-21']),
            $this->claim(13, ['country' => 1]), // без хотел — държавата от dbo.claim.state
        ];
        $fake->services = [
            $this->service(1, 10), $this->service(2, 10, ['hotel' => null, 'supplier' => 903, 'country' => 1, 'sale' => '.45', 'net' => '.40']),
            $this->service(3, 11), $this->service(4, 12), $this->service(5, 13, ['hotel' => null, 'supplier' => 903]),
        ];
        (new SamoSync($fake))->run(true);

        $c10 = DB::table('claims')->where('samo_inc', 10)->first();
        $this->assertSame('own', $c10->channel);
        $this->assertEquals(1000.45, (float) $c10->sale);
        $this->assertEquals(150.05, (float) $c10->margin);
        $this->assertEquals(501, $c10->hotel);
        $this->assertEquals(21, $c10->town);   // от картата на хотела
        $this->assertEquals(2, $c10->country); // от картата на града
        $this->assertSame('2026-03', $c10->created_month);
        $this->assertEquals(122, $c10->lead_days);

        $c11 = DB::table('claims')->where('samo_inc', 11)->first();
        $this->assertSame('agency', $c11->channel);
        $this->assertEquals(80, (float) $c11->commission);
        $this->assertEquals(70, (float) $c11->margin);

        $c12 = DB::table('claims')->where('samo_inc', 12)->first();
        $this->assertEquals(1, $c12->cancelled);
        $this->assertEquals(10, $c12->cancel_lead_days);

        $this->assertEquals(1, DB::table('partners')->where('samo_inc', 901)->value('is_turkish'));
        $this->assertEquals(0, DB::table('partners')->where('samo_inc', 903)->value('is_turkish'));
        $this->assertEquals(1, DB::table('partners')->where('samo_inc', 903)->value('is_supplier'));
        $this->assertEquals(1, DB::table('claims')->where('samo_inc', 13)->value('country'));
        $this->assertSame('Хотел А', DB::table('places')->where('kind', 'hotel')->value('name'));

        // повторно теглене: изтритата в SAMO услуга изчезва; ръчно махнатият „турски“ остава махнат
        DB::table('partners')->where('samo_inc', 901)->update(['is_turkish' => false, 'turkish_manual' => true]);
        $fake->services = array_values(array_filter($fake->services, fn ($s) => $s['inc'] !== 2));
        (new SamoSync($fake))->run(true);
        $this->assertSame(4, DB::table('services')->count());
        $this->assertEquals(1000, (float) DB::table('claims')->where('samo_inc', 10)->value('sale'));
        $this->assertEquals(0, DB::table('partners')->where('samo_inc', 901)->value('is_turkish'));
        $this->assertSame(2, DB::table('sync_runs')->whereNotNull('finished_at')->count());
    }

    public function test_unconverted_currency_is_flagged_and_not_summed(): void
    {
        $fake = new FakeSamo;
        $fake->claims = [$this->claim(20)];
        $fake->services = [$this->service(1, 20, ['sale' => null, 'net' => null, 'net_currency' => 'USD', 'net_original' => '300.00', 'unconverted' => true])];
        (new SamoSync($fake))->run(true);
        $c = DB::table('claims')->where('samo_inc', 20)->first();
        $this->assertEquals(1, $c->unconverted);
        $this->assertEquals(0, (float) $c->sale);
    }
}
