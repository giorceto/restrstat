<?php

namespace App\Console\Commands;

use App\Services\Samo\SamoSync;
use Illuminate\Console\Command;
use Throwable;

class SamoSyncCommand extends Command
{
    protected $signature = 'samo:sync {--all : всички заявки (първото пускане)} {--days= : колко дни назад (по подразбиране SAMO_SYNC_DAYS)}';

    protected $description = 'Тегли заявките и услугите от SAMO-Tour в нашата база (SAMO само се чете)';

    public function handle(SamoSync $sync): int
    {
        try {
            $run = $sync->run((bool) $this->option('all'), $this->option('days') !== null ? (int) $this->option('days') : null,
                fn (string $m) => $this->output->isVerbose() || $this->option('all') ? $this->line($m) : null);
        } catch (Throwable $e) {
            $this->error('Тегленето спря: '.$e->getMessage());

            return self::FAILURE;
        }
        $this->info("Готово: {$run->claims} заявки, {$run->services} услуги.");

        return self::SUCCESS;
    }
}
