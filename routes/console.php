<?php

use Illuminate\Support\Facades\Schedule;

// тегленето от SAMO — всеки час (cron: * * * * * php artisan schedule:run)
if (config('restrstat.sync')) {
    Schedule::command('samo:sync')->hourlyAt(17)->withoutOverlapping(120);
    // веднъж седмично — всичко (за поправки по стари заявки)
    Schedule::command('samo:sync --all')->weeklyOn(0, '03:40')->withoutOverlapping(240);
}
