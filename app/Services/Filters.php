<?php

namespace App\Services;

use Carbon\CarbonImmutable;
use Illuminate\Database\Query\Builder;
use Illuminate\Http\Request;

/** Общите филтри: период, по дата на продажба или на настаняване, канал. */
final class Filters
{
    public const CHANNELS = ['all' => 'Всички', 'own' => 'Нашите офиси', 'online' => 'Онлайн (сайтът)', 'agency' => 'Външни агенции'];

    public const BASES = ['created' => 'по дата на продажба', 'travel' => 'по дата на настаняване'];

    public function __construct(
        public CarbonImmutable $from,
        public CarbonImmutable $to,
        public string $basis = 'created',
        public string $channel = 'all',
    ) {}

    public static function fromRequest(Request $r, string $defaultBasis = 'created'): self
    {
        $year = CarbonImmutable::today()->startOfYear();
        $from = self::date($r->query('from')) ?? $year;
        $to = self::date($r->query('to')) ?? $year->endOfYear();
        if ($to < $from) {
            [$from, $to] = [$to, $from];
        }
        $basis = array_key_exists((string) $r->query('basis'), self::BASES) ? (string) $r->query('basis') : $defaultBasis;
        $channel = array_key_exists((string) $r->query('channel'), self::CHANNELS) ? (string) $r->query('channel') : 'all';

        return new self($from, $to, $basis, $channel);
    }

    /** гггг-мм-дд или дд.мм.гггг */
    public static function date(mixed $v): ?CarbonImmutable
    {
        if (! is_string($v)) {
            return null;
        }
        $v = trim($v);
        if (preg_match('/^(\d{1,2})\.(\d{1,2})\.(\d{4})$/', $v, $m)) {
            $v = sprintf('%04d-%02d-%02d', $m[3], $m[2], $m[1]);
        }
        if (! preg_match('/^(\d{4})-(\d{2})-(\d{2})$/', $v, $m) || ! checkdate((int) $m[2], (int) $m[3], (int) $m[1])) {
            return null;
        }

        return CarbonImmutable::createFromFormat('!Y-m-d', $v);
    }

    /** Същият период година по-рано. */
    public function lastYear(): self
    {
        return new self($this->from->subYear(), $this->to->subYear(), $this->basis, $this->channel);
    }

    public function column(): string
    {
        return $this->basis === 'travel' ? 'date_from' : 'created_at';
    }

    public function monthColumn(): string
    {
        return $this->basis === 'travel' ? 'from_month' : 'created_month';
    }

    public function apply(Builder $q, string $alias = 'claims'): Builder
    {
        $col = "$alias.".$this->column();
        $q->where($col, '>=', $this->basis === 'travel' ? $this->from->toDateString() : $this->from->format('Y-m-d 00:00:00'))
            ->where($col, '<=', $this->basis === 'travel' ? $this->to->toDateString() : $this->to->format('Y-m-d 23:59:59'));
        if ($this->channel !== 'all') {
            $q->where("$alias.channel", $this->channel);
        }

        return $q;
    }

    /** @return array<string, string> */
    public function query(array $extra = []): array
    {
        return array_merge(['from' => $this->from->toDateString(), 'to' => $this->to->toDateString(),
            'basis' => $this->basis, 'channel' => $this->channel], $extra);
    }
}
