<?php

namespace App\Console\Commands;

use Database\Seeders\DemoSeeder;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

class DemoCommand extends Command
{
    protected $signature = 'demo {action : load — измислени данни за проба; clear — изтрива всички данни от SAMO}';

    protected $description = 'Измислени данни за проба (без SAMO) или изчистване преди първото истинско теглене';

    public function handle(): int
    {
        $action = $this->argument('action');
        if (! in_array($action, ['load', 'clear'], true)) {
            $this->error('load или clear');

            return self::FAILURE;
        }
        if (! $this->confirm('Това изтрива заявките, услугите, партньорите и местата в нашата база (SAMO не се пипа). Продължаваме?', true)) {
            return self::FAILURE;
        }
        foreach (['services', 'claims', 'partners', 'places', 'sync_runs'] as $t) {
            DB::table($t)->delete();
        }
        if ($action === 'load') {
            (new DemoSeeder)->run();
            $this->info('Заредени са измислени данни.');
        } else {
            $this->info('Изчистено. Сега: php artisan samo:sync --all');
        }

        return self::SUCCESS;
    }
}
