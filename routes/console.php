<?php

use App\Services\CentroBackupService;
use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
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
        Storage::disk('local')->setVisibility($file->path, 'private');
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
        Storage::disk('local')->setVisibility($path, 'private');
        Storage::disk('public')->delete($path);
        $moved++;
    }

    $this->info("File migrati nello storage privato: {$moved}");
    if ($missing > 0) {
        $this->warn("Riferimenti senza file trovato: {$missing}");
    }

    return Command::SUCCESS;
})->purpose('Sposta upload pubblici esistenti nello storage privato');

Artisan::command('centro:import-1password-csv
    {file : Percorso del CSV esportato da 1Password}
    {--vault=Import 1Password : Nome cassaforte di destinazione}
    {--user-email=luigi.pistone@lu3g.it : Utente creatore}
    {--limit=0 : Importa solo le prime N righe valide}
    {--dry-run : Legge il CSV senza scrivere nel database}
    {--skip-duplicates : Salta password gia presenti con stesso titolo, username e URL nella cassaforte}',
    function () {
        $file = (string) $this->argument('file');
        $vaultName = trim((string) $this->option('vault')) ?: 'Import 1Password';
        $userEmail = trim((string) $this->option('user-email'));
        $limit = max(0, (int) $this->option('limit'));
        $dryRun = (bool) $this->option('dry-run');
        $skipDuplicates = (bool) $this->option('skip-duplicates');

        if (! Schema::hasTable('password_vaults') || ! Schema::hasTable('password_items')) {
            $this->error('Tabelle password non presenti. Esegui prima le migration.');

            return Command::FAILURE;
        }

        if (! is_file($file) || ! is_readable($file)) {
            $this->error('File CSV non trovato o non leggibile.');

            return Command::FAILURE;
        }

        $handle = fopen($file, 'rb');
        if (! $handle) {
            $this->error('Impossibile aprire il file CSV.');

            return Command::FAILURE;
        }

        $headers = fgetcsv($handle);
        if (! is_array($headers)) {
            fclose($handle);
            $this->error('CSV vuoto o non valido.');

            return Command::FAILURE;
        }

        $headers = array_map(fn ($header) => trim((string) preg_replace('/^\xEF\xBB\xBF/', '', (string) $header)), $headers);
        $normalizedHeaders = collect($headers)->mapWithKeys(fn ($header, $index) => [Str::lower($header) => $index]);
        $headerFor = function (array $names) use ($normalizedHeaders): ?int {
            foreach ($names as $name) {
                $index = $normalizedHeaders->get(Str::lower($name));
                if ($index !== null) {
                    return $index;
                }
            }

            return null;
        };

        $indexes = [
            'title' => $headerFor(['Title', 'Titolo']),
            'url' => $headerFor(['Url', 'URL']),
            'username' => $headerFor(['Username', 'Nome Utente']),
            'password' => $headerFor(['Password']),
            'notes' => $headerFor(['Notes', 'Note']),
            'type' => $headerFor(['Tipo']),
            'otp' => $headerFor(['OTPAuth', 'Prima Password Monouso']),
        ];

        foreach (['title', 'password'] as $required) {
            if ($indexes[$required] === null) {
                fclose($handle);
                $this->error("Colonna obbligatoria mancante: {$required}");

                return Command::FAILURE;
            }
        }

        $creator = $userEmail !== ''
            ? DB::table('users')->where('email', $userEmail)->first()
            : null;

        $vault = DB::table('password_vaults')->where('name', $vaultName)->first();
        $vaultId = $vault?->id ?? (string) Str::uuid();
        $now = now();

        $read = 0;
        $valid = 0;
        $imported = 0;
        $skipped = 0;
        $duplicates = 0;

        DB::transaction(function () use (
            $handle,
            $indexes,
            $vault,
            $vaultId,
            $vaultName,
            $creator,
            $now,
            $dryRun,
            $skipDuplicates,
            $limit,
            &$read,
            &$valid,
            &$imported,
            &$skipped,
            &$duplicates
        ) {
            if (! $dryRun && ! $vault) {
                DB::table('password_vaults')->insert([
                    'id' => $vaultId,
                    'name' => $vaultName,
                    'description' => 'Import automatico da CSV 1Password.',
                    'color' => '#0B6EF3',
                    'visibility' => 'personal',
                    'created_by' => $creator?->id,
                    'created_at' => $now,
                    'updated_at' => $now,
                ]);
            }

            while (($row = fgetcsv($handle)) !== false) {
                $read++;
                $value = fn (string $key) => trim((string) ($row[$indexes[$key]] ?? ''));
                $title = $value('title');
                $password = $value('password');

                if ($title === '' || $password === '') {
                    $skipped++;
                    continue;
                }

                $valid++;
                if ($limit > 0 && $imported >= $limit) {
                    continue;
                }

                $url = $indexes['url'] !== null ? $value('url') : '';
                $username = $indexes['username'] !== null ? $value('username') : '';

                if ($skipDuplicates) {
                    $exists = DB::table('password_items')
                        ->where('password_vault_id', $vaultId)
                        ->where('title', $title)
                        ->where(function ($query) use ($username) {
                            $username === ''
                                ? $query->whereNull('username')->orWhere('username', '')
                                : $query->where('username', $username);
                        })
                        ->where(function ($query) use ($url) {
                            $url === ''
                                ? $query->whereNull('url')->orWhere('url', '')
                                : $query->where('url', $url);
                        })
                        ->exists();

                    if ($exists) {
                        $duplicates++;
                        continue;
                    }
                }

                if ($dryRun) {
                    $imported++;
                    continue;
                }

                $tags = collect([
                    $indexes['type'] !== null ? $value('type') : '',
                    $indexes['otp'] !== null && $value('otp') !== '' ? 'OTP' : '',
                ])->filter()->unique()->values()->all();

                $itemPayload = [
                    'id' => (string) Str::uuid(),
                    'password_vault_id' => $vaultId,
                    'title' => $title,
                    'url' => $url ?: null,
                    'username' => $username ?: null,
                    'encrypted_password' => Crypt::encryptString($password),
                    'notes' => ($indexes['notes'] !== null ? $value('notes') : '') ?: null,
                    'tags' => json_encode($tags),
                    'custom_fields' => json_encode([]),
                    'expires_at' => null,
                    'favorite' => false,
                    'client_id' => null,
                    'project_id' => null,
                    'created_by' => $creator?->id,
                    'updated_by' => $creator?->id,
                    'created_at' => $now,
                    'updated_at' => $now,
                ];

                if (Schema::hasColumn('password_items', 'compromised_count')) {
                    $itemPayload['compromised_count'] = 0;
                }

                if (Schema::hasColumn('password_items', 'compromised_checked_at')) {
                    $itemPayload['compromised_checked_at'] = null;
                }

                DB::table('password_items')->insert($itemPayload);

                $imported++;
            }
        });

        fclose($handle);

        $this->info($dryRun ? 'Dry-run completato. Nessuna scrittura effettuata.' : 'Import completato.');
        $this->line("Righe lette: {$read}");
        $this->line("Righe valide: {$valid}");
        $this->line(($dryRun ? 'Importabili' : 'Importate').": {$imported}");
        $this->line("Saltate per dati mancanti: {$skipped}");
        $this->line("Duplicate saltate: {$duplicates}");
        $this->line("Cassaforte: {$vaultName}");

        return Command::SUCCESS;
    }
)->purpose('Importa password da un CSV esportato da 1Password');

Schedule::command('centro:backup weekly')->weeklyOn(1, '03:00');
Schedule::command('centro:backup monthly')->monthlyOn(1, '03:30');
