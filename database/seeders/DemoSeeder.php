<?php

namespace Database\Seeders;

use Carbon\CarbonImmutable;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

/** Измислени данни за проба — без връзка със SAMO и без никакви лични данни. */
class DemoSeeder extends Seeder
{
    public function run(): void
    {
        mt_srand(20261112);
        $countries = [1 => 'Гърция', 2 => 'Турция', 3 => 'Египет', 4 => 'България', 5 => 'Кипър'];
        $towns = [11 => [1, 'Халкидики'], 12 => [1, 'Тасос'], 13 => [1, 'Крит'], 21 => [2, 'Анталия'], 22 => [2, 'Бодрум'],
            23 => [2, 'Кушадасъ'], 31 => [3, 'Хургада'], 41 => [4, 'Слънчев бряг'], 51 => [5, 'Айя Напа']];
        $places = [];
        foreach ($countries as $id => $n) {
            $places[] = ['kind' => 'country', 'samo_inc' => $id, 'name' => $n, 'parent' => null];
        }
        $hotels = [];
        $h = 100;
        foreach ($towns as $id => [$c, $n]) {
            $places[] = ['kind' => 'town', 'samo_inc' => $id, 'name' => $n, 'parent' => $c];
            for ($i = 1; $i <= 4; $i++) {
                $h++;
                $hotels[$h] = [$id, $c];
                $places[] = ['kind' => 'hotel', 'samo_inc' => $h, 'name' => "Демо хотел $n $i*", 'parent' => $id];
            }
        }
        DB::table('places')->insert($places);

        // доставчици: турските — за Турция, останалите — за другите държави
        $suppliers = [901 => ['Демо TR Travel', 2], 902 => ['Демо Anatolia DMC', 2], 903 => ['Демо Aegean Partner', 1], 904 => ['Демо Nile Tours', 3], 905 => ['Демо BG Hotels', 4], 906 => ['Демо Cyprus DMC', 5]];
        $partners = [];
        foreach ([1 => 'TC', 13 => 'TC Sofia', 20 => 'Турс Клуб Елада — Пловдив', 34 => 'Турс Клуб Елада — Бургас', 177 => 'TC Online', 700 => 'Демо агенция 1', 701 => 'Демо агенция 2', 702 => 'Демо агенция 3'] as $id => $n) {
            $partners[] = ['samo_inc' => $id, 'name' => $n, 'country' => 4, 'is_turkish' => false, 'turkish_manual' => false, 'is_supplier' => false, 'synced_at' => now()];
        }
        foreach ($suppliers as $id => [$n, $c]) {
            $partners[] = ['samo_inc' => $id, 'name' => $n, 'country' => $c, 'is_turkish' => $c === 2, 'turkish_manual' => false, 'is_supplier' => true, 'synced_at' => now()];
        }
        DB::table('partners')->insert($partners);
        $supplierFor = fn (int $country) => match ($country) {
            2 => mt_rand(0, 1) ? 901 : 902, 1 => 903, 3 => 904, 4 => 905, default => 906
        };

        $clients = [1, 1, 13, 13, 20, 34, 177, 177, 177, 700, 701, 702];
        $claims = [];
        $services = [];
        $inc = 50000;
        $svc = 800000;
        $start = CarbonImmutable::create(2025, 1, 1);
        $days = (int) $start->diffInDays(CarbonImmutable::today());
        for ($n = 0; $n < 4000; $n++) {
            $inc++;
            $created = $start->addDays(mt_rand(0, $days))->setTime(mt_rand(8, 19), mt_rand(0, 59));
            // сезон: май–октомври
            $from = $created->addDays(mt_rand(3, 200));
            if ((int) $from->format('n') < 5 || (int) $from->format('n') > 10) {
                $from = $from->setDate($from->year + ((int) $from->format('n') > 10 ? 1 : 0), mt_rand(5, 10), mt_rand(1, 28));
            }
            $nights = [4, 5, 7, 7, 7, 10, 14][mt_rand(0, 6)];
            $to = $from->addDays($nights);
            $hotel = array_rand($hotels);
            [$town, $country] = $hotels[$hotel];
            $pax = mt_rand(1, 4);
            $partner = $clients[mt_rand(0, count($clients) - 1)];
            $channel = $partner === 177 ? 'online' : (in_array($partner, [1, 13, 20, 34], true) ? 'own' : 'agency');
            $net = round($pax * $nights * mt_rand(35, 110), 2);
            $sale = round($net * (1 + mt_rand(8, 22) / 100), 2);
            $pct = $channel === 'agency' ? 8 : null;
            $commission = $pct ? round($sale * $pct / 100, 2) : 0;
            $cancelled = mt_rand(1, 100) <= 9;
            $cancelOn = $cancelled ? $created->addDays(mt_rand(1, max(1, (int) $created->diffInDays($from)))) : null;
            if ($cancelOn && $cancelOn > $from) {
                $cancelOn = $from;
            }
            $claims[] = ['samo_inc' => $inc, 'partner' => $partner, 'channel' => $channel, 'created_at' => $created->format('Y-m-d H:i:s'),
                'created_month' => $created->format('Y-m'), 'date_from' => $from->toDateString(), 'date_to' => $to->toDateString(),
                'from_month' => $from->format('Y-m'), 'nights' => $nights, 'lead_days' => (int) $created->startOfDay()->diffInDays($from),
                'status' => $cancelled ? 3 : 2, 'cancelled' => $cancelled, 'cancelled_on' => $cancelOn?->toDateString(),
                'cancel_lead_days' => $cancelOn ? (int) $cancelOn->diffInDays($from) : null, 'pax' => $pax,
                'country' => $country, 'town' => $town, 'hotel' => $hotel, 'sale' => $sale + 30 * $pax, 'net' => $net + 25 * $pax,
                'commission' => $commission, 'margin' => round($sale + 30 * $pax - $net - 25 * $pax - $commission, 2),
                'commission_pct' => $pct, 'unconverted' => false, 'changed_at' => null, 'synced_at' => now()];
            $services[] = ['samo_inc' => ++$svc, 'claim' => $inc, 'supplier' => $supplierFor($country), 'country' => $country, 'town' => $town,
                'hotel' => $hotel, 'date_from' => $from->toDateString(), 'date_to' => $to->toDateString(), 'status' => null,
                'sale' => $sale, 'net' => $net, 'net_currency' => '€', 'net_original' => $net, 'unconverted' => false];
            // трансфер/застраховка без доставчик-хотел
            $services[] = ['samo_inc' => ++$svc, 'claim' => $inc, 'supplier' => $country === 2 ? $supplierFor(2) : null, 'country' => $country,
                'town' => $town, 'hotel' => null, 'date_from' => $from->toDateString(), 'date_to' => $from->toDateString(), 'status' => null,
                'sale' => 30 * $pax, 'net' => 25 * $pax, 'net_currency' => '€', 'net_original' => 25 * $pax, 'unconverted' => false];
        }
        foreach (array_chunk($claims, 500) as $c) {
            DB::table('claims')->insert($c);
        }
        foreach (array_chunk($services, 500) as $c) {
            DB::table('services')->insert($c);
        }
    }
}
