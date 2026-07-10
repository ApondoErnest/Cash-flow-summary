<?php

use App\Console\Commands\CleanupExportsCommand;
use App\Console\Commands\CleanupVerificationsCommand;
use App\Console\Commands\DispatchScheduledWhatsAppSummariesCommand;
use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

Schedule::command(CleanupVerificationsCommand::class)->everyFifteenMinutes();
Schedule::command(CleanupExportsCommand::class)->hourly();
Schedule::command(DispatchScheduledWhatsAppSummariesCommand::class)->everyMinute();
