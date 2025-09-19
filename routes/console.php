<?php

use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');


Schedule::command('knowledge:sync')->hourly();
// Экспорт незавершенных диалогов в CRM каждые 30 минут
Schedule::command('crm:sync export --limit=50')
    ->everyThirtyMinutes()
    ->withoutOverlapping()
    ->runInBackground();
// Статистика CRM синхронизации раз в день
Schedule::command('crm:sync stats')
    ->dailyAt('09:00')
    ->emailOutputTo(config('mail.admin_email'));