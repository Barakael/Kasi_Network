<?php

use App\Console\Commands\ArchiveRadacct;
use App\Console\Commands\EnforceQuotas;
use App\Console\Commands\ManageRadacctPartitions;
use App\Console\Commands\ReconcileOrders;
use App\Console\Commands\RollupAccounting;
use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

Schedule::command(RollupAccounting::class)->everyMinute()->withoutOverlapping(5);
Schedule::command(EnforceQuotas::class)->everyMinute()->withoutOverlapping(5);
Schedule::command(ReconcileOrders::class)->everyFiveMinutes()->withoutOverlapping(10);
Schedule::command(ArchiveRadacct::class)->hourly()->withoutOverlapping(30);
Schedule::command(ManageRadacctPartitions::class)->daily();
