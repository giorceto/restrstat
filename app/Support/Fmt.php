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

    /** С центовете — за задълженията и плащанията. */
    public static function eur2(int|float|null $v): string
    {
        return $v === null ? '—' : self::num($v, 2).' €';
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
        $sign = $v > 0 ? '▲ ' : ($v < 0 ? '▼ ' : '');

        return '<span class="delta '.$cls.'">'.$sign.self::num(abs($v), 1).' %</span>';
    }

    /** Промяна като значка (зелена/червена). */
    public static function badge(int|float|null $v): string
    {
        if ($v === null) {
            return '';
        }
        $cls = $v > 0 ? 'up' : ($v < 0 ? 'down' : 'flat');
        $sign = $v > 0 ? '▲ ' : ($v < 0 ? '▼ ' : '');

        return '<span class="badge '.$cls.'">'.$sign.self::num(abs($v), 1).' %</span>';
    }

    /**
     * Мини графика (стълбчета) като SVG; $current — индексът на текущия месец (по-тъмен).
     *
     * @param  list<int|float>  $values
     */
    public static function spark(array $values, ?int $current = null): string
    {
        $n = count($values);
        if ($n === 0) {
            return '';
        }
        $max = max(array_map('floatval', $values)) ?: 1;
        $w = 220 / $n;
        $out = '<svg class="spark" viewBox="0 0 220 34" preserveAspectRatio="none" aria-hidden="true">';
        foreach (array_values($values) as $i => $v) {
            $h = max(2, round(34 * (float) $v / $max, 1));
            $out .= sprintf('<rect%s x="%.1f" y="%.1f" width="%.1f" height="%.1f" rx="2"/>',
                $i === $current ? ' class="cur"' : '', $i * $w + 1.5, 34 - $h, max(1, $w - 3), $h);
        }

        return $out.'</svg>';
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
