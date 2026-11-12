<?php

namespace App\Services\Samo;

use Illuminate\Database\ConnectionInterface;
use Illuminate\Support\Facades\DB;

/**
 * Чете от базата на SAMO-Tour (connections.samo). САМО SELECT. Колоните на dbo.[order] и dbo.partner се проверяват в
 * sys.columns — липсващите стават NULL, така че тегленето не пада, ако някоя се казва другояче (виж config/restrstat.php).
 */
class SqlSamoSource implements SamoSource
{
    /** Празно число в SAMO. */
    public const EMPTY_INT = -2147483647;

    /** @var array<string, list<string>> */
    private array $columns = [];

    public function __construct(private ?ConnectionInterface $db = null)
    {
        $this->db ??= DB::connection('samo');
    }

    public function claims(int $afterInc, int $limit, ?string $since = null): array
    {
        $pax = $this->hasColumn('dbo.people', 'claim')
            ? 'OUTER APPLY (SELECT COUNT(*) AS pax FROM dbo.people pe WHERE pe.claim = cl.inc) p'
            : 'OUTER APPLY (SELECT CAST(NULL AS int) AS pax) p';
        $where = 'cl.inc > ?';
        $bind = [$afterInc];
        if ($since !== null) {
            $where .= ' AND (cl.cdatetime >= ? OR cl.edate >= ?)';
            array_push($bind, $since, $since);
        }
        // claim.contact (имейлът на клиента) нарочно НЕ се чете
        $rows = $this->db->select("
            SELECT TOP ($limit) cl.inc, cl.partner, cl.author,
                   CONVERT(char(19), cl.cdatetime, 120) AS created_at,
                   CONVERT(char(10), cl.datebeg, 23) AS date_from, CONVERT(char(10), cl.dateend, 23) AS date_to,
                   cl.nights, cl.status, CONVERT(char(10), cl.canceldate, 23) AS cancelled_on,
                   CAST(cl.commission AS varchar(20)) AS commission_pct,
                   CONVERT(char(19), cl.edate, 120) AS changed_at, p.pax
            FROM dbo.claim cl
            $pax
            WHERE $where
            ORDER BY cl.inc", $bind);

        return array_map(fn ($r) => [
            'inc' => (int) $r->inc,
            'partner' => self::int($r->partner),
            'author' => self::int($r->author),
            'created_at' => trim((string) $r->created_at),
            'date_from' => self::date($r->date_from),
            'date_to' => self::date($r->date_to),
            'nights' => self::int($r->nights),
            'status' => self::int($r->status),
            'cancelled_on' => self::date($r->cancelled_on),
            'commission_pct' => self::int($r->commission_pct) === null ? null : trim((string) $r->commission_pct),
            'changed_at' => $r->changed_at ? trim((string) $r->changed_at) : null,
            'pax' => self::int($r->pax),
        ], $rows);
    }

    public function services(array $claims): array
    {
        if (! $claims) {
            return [];
        }
        $col = function (string $key, string $cast = 'int') {
            $name = config("restrstat.order_columns.$key");
            if (! $name || ! $this->hasColumn('dbo.order', $name)) {
                return "CAST(NULL AS $cast)";
            }

            return $cast === 'date' ? "CONVERT(char(10), y.[$name], 23)" : "y.[$name]";
        };
        $rate = (string) config('restrstat.bgn_rate');
        $eur = fn (string $v, string $c) => "CAST(CASE WHEN $c.alias = N'€' THEN y.$v WHEN $c.alias = N'лв.' THEN y.$v / $rate END AS decimal(12, 2))";
        $in = implode(',', array_map('intval', $claims));

        $rows = $this->db->select("
            SELECT y.inc, y.claim, {$col('supplier')} AS supplier, {$col('country')} AS country, {$col('town')} AS town,
                   {$col('hotel')} AS hotel, {$col('date_from', 'date')} AS date_from, {$col('date_to', 'date')} AS date_to,
                   {$col('status')} AS status,
                   CAST({$eur('cost', 'cc')} AS varchar(20)) AS sale, CAST({$eur('net', 'nc')} AS varchar(20)) AS net,
                   nc.alias AS net_currency, CAST(CAST(y.net AS decimal(12, 2)) AS varchar(20)) AS net_original,
                   CASE WHEN (y.cost <> 0 AND (cc.alias IS NULL OR cc.alias NOT IN (N'€', N'лв.')))
                          OR (y.net <> 0 AND (nc.alias IS NULL OR nc.alias NOT IN (N'€', N'лв.'))) THEN 1 ELSE 0 END AS unconverted
            FROM dbo.[order] y
            LEFT JOIN dbo.currency cc ON cc.inc = y.ccurrency
            LEFT JOIN dbo.currency nc ON nc.inc = y.ncurrency
            WHERE y.claim IN ($in)
            ORDER BY y.claim, y.inc");

        return array_map(fn ($r) => [
            'inc' => (int) $r->inc,
            'claim' => (int) $r->claim,
            'supplier' => self::int($r->supplier),
            'country' => self::int($r->country),
            'town' => self::int($r->town),
            'hotel' => self::int($r->hotel),
            'date_from' => self::date($r->date_from),
            'date_to' => self::date($r->date_to),
            'status' => self::int($r->status),
            'sale' => self::money($r->sale),
            'net' => self::money($r->net),
            'net_currency' => $r->net_currency !== null ? trim((string) $r->net_currency) : null,
            'net_original' => self::money($r->net_original),
            'unconverted' => (bool) $r->unconverted,
        ], $rows);
    }

    public function partners(array $incs): array
    {
        if (! $incs) {
            return [];
        }
        $c = config('restrstat.partner_country_column');
        $country = $c && $this->hasColumn('dbo.partner', $c) ? "p.[$c]" : 'CAST(NULL AS int)';
        $in = implode(',', array_map('intval', $incs));
        $rows = $this->db->select("SELECT p.inc, p.name, $country AS country FROM dbo.partner p WHERE p.inc IN ($in)");

        return array_map(fn ($r) => ['inc' => (int) $r->inc, 'name' => self::text($r->name), 'country' => self::int($r->country)], $rows);
    }

    public function places(string $kind, array $incs): array
    {
        [$table, $parent] = match ($kind) {
            'country' => ['dbo.state', null],
            'town' => ['dbo.town', 'state'],
            'hotel' => ['dbo.hotel', 'town'],
        };
        if (! $incs || ! $this->hasColumn($table, 'name')) {
            return [];
        }
        $p = $parent && $this->hasColumn($table, $parent) ? "t.[$parent]" : 'CAST(NULL AS int)';
        $in = implode(',', array_map('intval', $incs));
        $rows = $this->db->select("SELECT t.inc, t.name, $p AS parent FROM $table t WHERE t.inc IN ($in)");

        return array_map(fn ($r) => ['inc' => (int) $r->inc, 'name' => self::text($r->name), 'parent' => self::int($r->parent)], $rows);
    }

    /** Колоните на таблица в SAMO (от sys.columns, без данни). */
    public function columns(string $table): array
    {
        $object = str_replace(['[', ']'], '', $table);

        return $this->columns[$object] ??= array_map(
            fn ($r) => strtolower($r->name),
            $this->db->select('SELECT name FROM sys.columns WHERE object_id = OBJECT_ID(?) ORDER BY column_id', ['[dbo].['.substr($object, 4).']'])
        );
    }

    private function hasColumn(string $table, string $column): bool
    {
        return in_array(strtolower($column), $this->columns($table), true);
    }

    public static function int(mixed $v): ?int
    {
        if ($v === null || trim((string) $v) === '') {
            return null;
        }
        $i = (int) $v;

        return $i === self::EMPTY_INT ? null : $i;
    }

    private static function date(mixed $v): ?string
    {
        $v = $v === null ? '' : trim((string) $v);

        // 1900-01-01 и подобни — „няма дата“
        return $v === '' || $v < '1901-01-01' ? null : $v;
    }

    private static function money(mixed $v): ?string
    {
        if ($v === null || trim((string) $v) === '') {
            return null;
        }
        $v = trim((string) $v);

        return str_starts_with($v, '.') ? '0'.$v : (str_starts_with($v, '-.') ? '-0'.substr($v, 1) : $v);
    }

    private static function text(mixed $v): ?string
    {
        $v = $v === null ? '' : trim((string) $v);

        return $v === '' ? null : mb_substr($v, 0, 190);
    }
}
