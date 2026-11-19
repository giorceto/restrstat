<?php

namespace App\Services;

use Illuminate\Support\Facades\DB;

/**
 * Пренасочва задълженията: услугите на доставчик „към хотела“ (напр. AYT transfers) се дължат на доставчика
 * на хотела в същата заявка (основният хотел — с най-голяма нетна цена). Без хотел в заявката — остават на него.
 */
class PayeeRouting
{
    public function apply(): void
    {
        DB::table('services')->update(['payee' => DB::raw('supplier')]);
        $follow = DB::table('partners')->where('follows_hotel', true)->pluck('samo_inc')->map(fn ($i) => (int) $i)->all();
        if (! $follow) {
            return;
        }
        DB::table('services')->whereIn('supplier', $follow)->select('claim')->distinct()->orderBy('claim')
            ->chunk(1000, function ($rows) use ($follow) {
                $claims = $rows->pluck('claim')->all();
                $hotelSupplier = [];
                $hotels = DB::table('services')->whereIn('claim', $claims)->whereNotNull('hotel')->whereNotNull('supplier')
                    ->whereNotIn('supplier', $follow)->orderBy('claim')->orderByDesc('net')->get(['claim', 'supplier']);
                foreach ($hotels as $h) {
                    $hotelSupplier[$h->claim] ??= (int) $h->supplier;
                }
                // по една заявка за всеки доставчик на хотел
                $byPayee = [];
                foreach ($hotelSupplier as $claim => $payee) {
                    $byPayee[$payee][] = $claim;
                }
                foreach ($byPayee as $payee => $list) {
                    DB::table('services')->whereIn('claim', $list)->whereIn('supplier', $follow)->update(['payee' => $payee]);
                }
            });
    }
}
