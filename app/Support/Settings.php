<?php

namespace App\Support;

use Illuminate\Support\Facades\DB;

/** Малки настройки, сменяни от страницата. */
final class Settings
{
    public static function get(string $key, ?string $default = null): ?string
    {
        return DB::table('settings')->where('key', $key)->value('value') ?? $default;
    }

    public static function set(string $key, ?string $value): void
    {
        DB::table('settings')->updateOrInsert(['key' => $key], ['value' => $value]);
    }
}
