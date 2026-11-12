<?php

namespace App\Support;

/** Числата по български: 12 345,67 €. */
final class Fmt
{
    public static function num(int|float|null $v, int $dec = 0): string
    {
        return $v === null ? '—' : number_format((float) $v, $dec, ',', ' ');
    }

    public static function eur(int|float|null $v): string
    {
        return $v === null ? '—' : self::num($v).' €';
    }

    public static function pct(int|float|null $v): string
    {
        return $v === null ? '—' : self::num($v, 1).' %';
    }

    /** Промяна спрямо миналата година, оцветена. */
    public static function delta(int|float|null $v): string
    {
        if ($v === null) {
            return '<span class="muted">—</span>';
        }
        $cls = $v > 0 ? 'up' : ($v < 0 ? 'down' : 'muted');
        $sign = $v > 0 ? '▲ +' : ($v < 0 ? '▼ ' : '');

        return '<span class="'.$cls.'">'.$sign.self::num($v, 1).' %</span>';
    }

    /** % промяна от $old до $new. */
    public static function change(int|float|null $new, int|float|null $old): ?float
    {
        return $old ? round(100 * ((float) $new - (float) $old) / (float) $old, 1) : null;
    }

    /** Данните за графика (canvas data-chart). */
    public static function chart(array $labels, array $series, string $unit = 'eur', string $type = 'bar'): string
    {
        return json_encode(['unit' => $unit, 'type' => $type, 'labels' => array_values($labels),
            'series' => array_map(fn ($label, $data) => ['label' => $label, 'data' => array_values($data)], array_keys($series), $series)],
            JSON_UNESCAPED_UNICODE);
    }

    public static function date(?string $d): string
    {
        return $d ? date('d.m.Y', strtotime($d)) : '—';
    }
}
