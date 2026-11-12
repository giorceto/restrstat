<?php

namespace App\Services\Samo;

/** Суми в цели центове — без float при събирането. */
final class Cents
{
    public static function from(?string $v): int
    {
        if ($v === null || $v === '') {
            return 0;
        }
        $neg = str_starts_with($v, '-');
        [$whole, $frac] = array_pad(explode('.', ltrim($v, '+-'), 2), 2, '');
        $frac = str_pad(substr($frac, 0, 3), 3, '0');
        $cents = (int) $whole * 100 + intdiv((int) $frac + 5, 10); // закръгляне до цент

        return $neg ? -$cents : $cents;
    }

    public static function str(int $cents): string
    {
        $sign = $cents < 0 ? '-' : '';
        $cents = abs($cents);

        return $sign.intdiv($cents, 100).'.'.str_pad((string) ($cents % 100), 2, '0', STR_PAD_LEFT);
    }
}
