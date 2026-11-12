<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class SyncRun extends Model
{
    public $timestamps = false;

    protected $guarded = [];

    protected $attributes = ['claims' => 0, 'services' => 0];

    protected function casts(): array
    {
        return ['started_at' => 'datetime', 'finished_at' => 'datetime', 'full' => 'boolean'];
    }

    public static function lastOk(): ?self
    {
        return static::whereNotNull('finished_at')->whereNull('error')->latest('id')->first();
    }
}
