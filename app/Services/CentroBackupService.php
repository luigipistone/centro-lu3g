<?php

namespace App\Services;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use RuntimeException;
use Symfony\Component\Process\Process;

class CentroBackupService
{
    public function create(string $frequency = 'manual'): object
    {
        $frequency = in_array($frequency, ['manual', 'weekly', 'monthly'], true) ? $frequency : 'manual';
        $id = (string) Str::uuid();
        $startedAt = now();
        $relativePath = sprintf('backups/centro-lu3g-%s-%s.sql', $frequency, $startedAt->format('Ymd-His'));

        DB::table('backup_runs')->insert([
            'id' => $id,
            'frequency' => $frequency,
            'status' => 'running',
            'started_at' => $startedAt,
            'storage_path' => $relativePath,
        ]);

        try {
            $tables = DB::select('SHOW TABLES');
            $dump = $this->dumpDatabase();

            Storage::disk('local')->put($relativePath, $dump);
            Storage::disk('local')->setVisibility($relativePath, 'private');

            DB::table('backup_runs')->where('id', $id)->update([
                'status' => 'completed',
                'finished_at' => now(),
                'tables_count' => count($tables),
                'size_bytes' => Storage::disk('local')->size($relativePath),
                'error' => null,
            ]);

            if (in_array($frequency, ['weekly', 'monthly'], true)) {
                $this->pruneAutomaticBackups($frequency);
            }
        } catch (\Throwable $exception) {
            DB::table('backup_runs')->where('id', $id)->update([
                'status' => 'failed',
                'finished_at' => now(),
                'error' => $exception->getMessage(),
            ]);

            throw $exception;
        }

        return DB::table('backup_runs')->where('id', $id)->first();
    }

    public function restore(string $backupId): void
    {
        $backup = DB::table('backup_runs')->where('id', $backupId)->first();

        if (! $backup || $backup->status !== 'completed') {
            throw new RuntimeException('Backup non disponibile per il ripristino.');
        }

        if (! $backup->storage_path || ! Storage::disk('local')->exists($backup->storage_path)) {
            throw new RuntimeException('File fisico del backup non trovato.');
        }

        $this->restoreDatabase(Storage::disk('local')->get($backup->storage_path));
    }

    public function delete(string $backupId): void
    {
        $backup = DB::table('backup_runs')->where('id', $backupId)->first();

        if (! $backup) {
            throw new RuntimeException('Backup non trovato.');
        }

        if ($backup->storage_path) {
            Storage::disk('local')->delete($backup->storage_path);
        }

        DB::table('backup_runs')->where('id', $backupId)->delete();
    }

    private function dumpDatabase(): string
    {
        $connection = config('database.connections.mysql');
        $command = [
            'mysqldump',
            '--single-transaction',
            '--quick',
            '--skip-lock-tables',
            '--routines',
            '--triggers',
            '--default-character-set=utf8mb4',
            '--host='.$connection['host'],
            '--port='.(string) ($connection['port'] ?? 3306),
            '--user='.$connection['username'],
            $connection['database'],
        ];

        $process = new Process($command, base_path(), ['MYSQL_PWD' => (string) $connection['password']], null, 120);
        $process->run();

        if (! $process->isSuccessful()) {
            throw new RuntimeException(trim($process->getErrorOutput()) ?: 'Errore durante la creazione del dump MySQL.');
        }

        return $process->getOutput();
    }

    private function restoreDatabase(string $dump): void
    {
        $connection = config('database.connections.mysql');
        $command = [
            'mysql',
            '--default-character-set=utf8mb4',
            '--host='.$connection['host'],
            '--port='.(string) ($connection['port'] ?? 3306),
            '--user='.$connection['username'],
            $connection['database'],
        ];

        $process = new Process($command, base_path(), ['MYSQL_PWD' => (string) $connection['password']], $dump, 120);
        $process->run();

        if (! $process->isSuccessful()) {
            throw new RuntimeException(trim($process->getErrorOutput()) ?: 'Errore durante il ripristino del backup MySQL.');
        }
    }

    private function pruneAutomaticBackups(string $frequency): void
    {
        $expired = DB::table('backup_runs')
            ->where('frequency', $frequency)
            ->where('status', 'completed')
            ->orderByDesc('started_at')
            ->skip(2)
            ->take(1000)
            ->get();

        foreach ($expired as $backup) {
            if ($backup->storage_path) {
                Storage::disk('local')->delete($backup->storage_path);
            }

            DB::table('backup_runs')->where('id', $backup->id)->delete();
        }
    }
}
