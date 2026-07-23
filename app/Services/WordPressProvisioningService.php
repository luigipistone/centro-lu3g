<?php

namespace App\Services;

use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Process;
use Illuminate\Support\Str;
use RuntimeException;

class WordPressProvisioningService
{
    public function run(string $provisioningId): void
    {
        $provisioning = DB::table('wordpress_provisionings')->where('id', $provisioningId)->first();
        if (! $provisioning || $provisioning->status === 'completed') {
            return;
        }

        $project = DB::table('projects')
            ->leftJoin('clients', 'clients.id', '=', 'projects.client_id')
            ->where('projects.id', $provisioning->project_id)
            ->first(['projects.id', 'projects.name', 'clients.name as client_name']);

        if (! $project) {
            $this->fail($provisioningId, 'Il progetto o il cliente non è più disponibile.');

            return;
        }

        DB::table('wordpress_provisionings')->where('id', $provisioningId)->update([
            'status' => 'running',
            'current_step' => 'preflight',
            'progress' => 2,
            'error_message' => null,
            'started_at' => $provisioning->started_at ?: now(),
            'updated_at' => now(),
        ]);

        $buffer = '';

        try {
            $result = Process::timeout((int) config('wordpress-provisioning.timeout', 2400))
                ->run([
                    'sudo',
                    (string) config('wordpress-provisioning.runner'),
                    'run',
                    '--folder',
                    $provisioning->folder_slug,
                    '--site-title',
                    $project->client_name ?: $project->name,
                ], function (string $type, string $output) use ($provisioningId, &$buffer) {
                    $buffer .= $output;
                    $lines = explode("\n", $buffer);
                    $buffer = array_pop($lines) ?? '';

                    foreach ($lines as $line) {
                        $this->consumeRunnerLine($provisioningId, trim($line));
                    }
                });

            if ($buffer !== '') {
                $this->consumeRunnerLine($provisioningId, trim($buffer));
            }

            if ($result->failed()) {
                throw new RuntimeException(trim($result->errorOutput() ?: $result->output()) ?: 'Provisioning WordPress non riuscito.');
            }

            $fresh = DB::table('wordpress_provisionings')->where('id', $provisioningId)->first();
            if (! $fresh || $fresh->status !== 'completed') {
                throw new RuntimeException('Il runner non ha restituito il risultato finale.');
            }
        } catch (\Throwable $exception) {
            $this->fail($provisioningId, $exception->getMessage());
        }
    }

    private function consumeRunnerLine(string $provisioningId, string $line): void
    {
        if ($line === '') {
            return;
        }

        if (str_starts_with($line, "CENTRO_PROGRESS\t")) {
            [, $progress, $step, $message] = array_pad(explode("\t", $line, 4), 4, '');
            $this->progress($provisioningId, (int) $progress, $step, $message);

            return;
        }

        if (str_starts_with($line, "CENTRO_RESULT\t")) {
            $encoded = substr($line, strlen("CENTRO_RESULT\t"));
            $payload = json_decode((string) base64_decode($encoded, true), true);
            if (! is_array($payload)) {
                throw new RuntimeException('Risultato finale del provisioning non valido.');
            }

            $this->complete($provisioningId, $payload);
        }
    }

    private function progress(string $provisioningId, int $progress, string $step, string $message): void
    {
        $record = DB::table('wordpress_provisionings')->where('id', $provisioningId)->first(['log']);
        $log = collect(preg_split('/\r\n|\r|\n/', (string) ($record->log ?? '')))
            ->filter()
            ->push(now('Europe/Rome')->format('d/m/Y H:i:s').' · '.$message)
            ->take(-80)
            ->implode("\n");

        DB::table('wordpress_provisionings')->where('id', $provisioningId)->update([
            'current_step' => $step,
            'progress' => max(0, min(99, $progress)),
            'log' => $log,
            'updated_at' => now(),
        ]);
    }

    private function complete(string $provisioningId, array $payload): void
    {
        foreach (['database_name', 'database_user', 'database_password', 'admin_username', 'admin_password', 'site_url'] as $required) {
            if (blank($payload[$required] ?? null)) {
                throw new RuntimeException("Dato finale mancante: {$required}.");
            }
        }

        DB::transaction(function () use ($provisioningId, $payload) {
            $provisioning = DB::table('wordpress_provisionings')->where('id', $provisioningId)->lockForUpdate()->first();
            if (! $provisioning || $provisioning->status === 'completed') {
                return;
            }

            $vault = DB::table('password_vaults')
                ->where('name', (string) config('wordpress-provisioning.vault_name', 'Generale'))
                ->first();

            if (! $vault) {
                throw new RuntimeException('La cassaforte Generale non è disponibile.');
            }

            $credentialId = (string) Str::uuid();
            DB::table('password_items')->insert([
                'id' => $credentialId,
                'password_vault_id' => $vault->id,
                'title' => $provisioning->folder_slug.' WordPress',
                'url' => $payload['site_url'],
                'username' => $payload['admin_username'],
                'encrypted_password' => Crypt::encryptString($payload['admin_password']),
                'notes' => '<p>Installazione WordPress generata automaticamente per il progetto.</p>',
                'tags' => json_encode(['WordPress', 'Provisioning']),
                'custom_fields' => json_encode([]),
                'favorite' => false,
                'client_id' => $provisioning->client_id,
                'project_id' => $provisioning->project_id,
                'created_by' => $provisioning->started_by,
                'updated_by' => $provisioning->started_by,
                'created_at' => now(),
                'updated_at' => now(),
            ]);

            DB::table('password_audit_logs')->insert([
                'id' => (string) Str::uuid(),
                'password_item_id' => $credentialId,
                'user_id' => $provisioning->started_by,
                'action' => 'created',
                'details' => 'Credenziale WordPress generata dal provisioning.',
                'created_at' => now(),
                'updated_at' => now(),
            ]);

            DB::table('wordpress_provisionings')->where('id', $provisioningId)->update([
                'credential_item_id' => $credentialId,
                'database_name' => $payload['database_name'],
                'database_user' => $payload['database_user'],
                'encrypted_database_password' => Crypt::encryptString($payload['database_password']),
                'site_url' => $payload['site_url'],
                'status' => 'completed',
                'current_step' => 'completed',
                'progress' => 100,
                'error_message' => null,
                'completed_at' => now(),
                'updated_at' => now(),
            ]);
        });
    }

    private function fail(string $provisioningId, string $message): void
    {
        DB::table('wordpress_provisionings')->where('id', $provisioningId)->update([
            'status' => 'failed',
            'error_message' => Str::limit(trim($message), 4000, ''),
            'updated_at' => now(),
        ]);
    }
}
