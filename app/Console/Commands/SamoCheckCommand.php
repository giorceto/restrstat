<?php

namespace App\Console\Commands;

use App\Services\Samo\SqlSamoSource;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Throwable;

/**
 * Проверка на връзката и разучаване на SAMO — само колони и бройки, без данни на клиенти.
 *   php artisan samo:check                 — връзка, само четене ли е, колоните на таблиците, които ползваме
 *   php artisan samo:check --find=hotel    — таблиците и колоните с „hotel“ в името
 *   php artisan samo:check --values=order.partner — кои стойности са най-чести в колона (само за числови колони)
 */
class SamoCheckCommand extends Command
{
    protected $signature = 'samo:check {--find= : част от име на таблица или колона} {--values= : таблица.колона — най-честите стойности (числови)}';

    protected $description = 'SAMO-Tour: проверка на връзката и колоните (само чете, без лични данни)';

    private const TABLES = ['dbo.claim', 'dbo.order', 'dbo.currency', 'dbo.partner', 'dbo.state', 'dbo.town', 'dbo.hotel', 'dbo.people'];

    public function handle(): int
    {
        $db = DB::connection('samo');
        try {
            $info = $db->selectOne('SELECT @@VERSION AS version, DB_NAME() AS db, SUSER_NAME() AS login');
        } catch (Throwable $e) {
            $this->error('Няма връзка: '.$e->getMessage());
            $this->line('Провери: tsql -S samo -U <потребител>; /etc/freetds/freetds.conf, раздел [samo]; SAMO_DB_* в .env.');

            return self::FAILURE;
        }
        $this->info('Връзката работи: '.strtok($info->version, "\n").' · база '.$info->db.' · '.$info->login);
        $w = $db->selectOne("SELECT IS_ROLEMEMBER('db_owner') AS o, IS_ROLEMEMBER('db_datawriter') AS w, IS_ROLEMEMBER('db_ddladmin') AS d");
        if ($w && ($w->o || $w->w || $w->d)) {
            $this->warn('ВНИМАНИЕ: потребителят може да пише в SAMO. Трябва да е само db_datareader.');
        }

        if ($find = $this->option('find')) {
            $like = '%'.str_replace(['[', '%', '_'], ['[[]', '[%]', '[_]'], $find).'%';
            $rows = $db->select('SELECT t.name AS tbl, c.name AS col, ty.name AS type,
                    (SELECT SUM(p.rows) FROM sys.partitions p WHERE p.object_id = t.object_id AND p.index_id IN (0, 1)) AS cnt
                FROM sys.tables t JOIN sys.columns c ON c.object_id = t.object_id JOIN sys.types ty ON ty.user_type_id = c.user_type_id
                WHERE t.name LIKE ? OR c.name LIKE ? ORDER BY t.name, c.column_id', [$like, $like]);
            $this->table(['Таблица', 'Редове', 'Колона', 'Тип'], array_map(fn ($r) => [$r->tbl, $r->cnt, $r->col, $r->type], $rows));

            return self::SUCCESS;
        }

        if ($v = $this->option('values')) {
            if (! preg_match('/^(\w+)\.(\w+)$/', $v, $m)) {
                $this->error('Формат: таблица.колона, напр. order.partner');

                return self::FAILURE;
            }
            $type = $db->selectOne('SELECT ty.name FROM sys.columns c JOIN sys.types ty ON ty.user_type_id = c.user_type_id
                WHERE c.object_id = OBJECT_ID(?) AND c.name = ?', ["[dbo].[$m[1]]", $m[2]]);
            if (! $type || ! in_array($type->name, ['int', 'smallint', 'tinyint', 'bigint', 'bit'], true)) {
                $this->error('Само за числови колони (без текст — да не излязат лични данни).');

                return self::FAILURE;
            }
            $rows = $db->select("SELECT TOP 30 [$m[2]] AS v, COUNT(*) AS cnt FROM dbo.[$m[1]] GROUP BY [$m[2]] ORDER BY COUNT(*) DESC");
            $this->table(['Стойност', 'Брой'], array_map(fn ($r) => [$r->v, $r->cnt], $rows));

            return self::SUCCESS;
        }

        $source = new SqlSamoSource($db);
        foreach (self::TABLES as $t) {
            $cols = $source->columns($t);
            $this->line("<info>$t</info>: ".($cols ? implode(', ', $cols) : '<comment>няма такава таблица</comment>'));
        }
        $this->newLine();
        $this->line('Колоните на услугите, които ползваме (config/restrstat.php → order_columns):');
        $orderCols = $source->columns('dbo.order');
        foreach (array_filter(config('restrstat.order_columns')) as $key => $col) {
            $this->line(sprintf('  %-10s → %-10s %s', $key, $col, in_array(strtolower($col), $orderCols, true) ? '✓' : '✗ липсва'));
        }

        $this->line('  town, country — от хотела (dbo.hotel.town → dbo.town.state) и от dbo.claim.state');

        return self::SUCCESS;
    }
}
