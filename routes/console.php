<?php

use App\Services\CentroBackupService;
use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;
use Symfony\Component\Console\Command\Command;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

Artisan::command('centro:backup {frequency=manual}', function (CentroBackupService $backupService) {
    $frequency = $this->argument('frequency');

    if (! in_array($frequency, ['manual', 'weekly', 'monthly'], true)) {
        $this->error('Tipologia backup non valida. Usa manual, weekly o monthly.');

        return Command::FAILURE;
    }

    $backup = $backupService->create($frequency);
    $this->info(sprintf('Backup %s completato: %s', $frequency, $backup->id));

    return Command::SUCCESS;
})->purpose('Crea un backup SQL del database Centro LU3G');

Schedule::command('centro:backup weekly')->weeklyOn(1, '03:00');
Schedule::command('centro:backup monthly')->monthlyOn(1, '03:30');
