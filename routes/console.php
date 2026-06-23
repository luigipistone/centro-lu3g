<?php

use App\Services\CentroBackupService;
use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;
use Illuminate\Support\Facades\Storage;
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

Artisan::command('centro:privatize-files', function () {
    $moved = 0;
    $missing = 0;

    foreach (DB::table('project_files')->get(['id', 'path']) as $file) {
        if (! $file->path || Storage::disk('local')->exists($file->path)) {
            continue;
        }

        if (! Storage::disk('public')->exists($file->path)) {
            $missing++;
            continue;
        }

        Storage::disk('local')->put($file->path, Storage::disk('public')->get($file->path));
        Storage::disk('public')->delete($file->path);
        $moved++;
    }

    foreach (DB::table('profiles')->whereNotNull('avatar_url')->pluck('avatar_url') as $avatarUrl) {
        if (! str_starts_with((string) $avatarUrl, '/avatars/')) {
            continue;
        }

        $path = 'avatars/'.basename((string) $avatarUrl);
        if (Storage::disk('local')->exists($path)) {
            continue;
        }

        if (! Storage::disk('public')->exists($path)) {
            $missing++;
            continue;
        }

        Storage::disk('local')->put($path, Storage::disk('public')->get($path));
        Storage::disk('public')->delete($path);
        $moved++;
    }

    $this->info("File migrati nello storage privato: {$moved}");
    if ($missing > 0) {
        $this->warn("Riferimenti senza file trovato: {$missing}");
    }

    return Command::SUCCESS;
})->purpose('Sposta upload pubblici esistenti nello storage privato');

Schedule::command('centro:backup weekly')->weeklyOn(1, '03:00');
Schedule::command('centro:backup monthly')->monthlyOn(1, '03:30');
