<?php

namespace App\Services;

use Illuminate\Http\Client\PendingRequest;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use RuntimeException;
use Throwable;

class WebRepositoryService
{
    private const ORGANIZATION = 'lu3gofficial';
    private const PROPERTY = 'Plugin_Wordpress';
    private const VALUE = 'yes';

    public function isConfigured(): bool
    {
        return filled(DB::table('github_settings')->value('encrypted_token'));
    }

    public function repositories(bool $fresh = false): array
    {
        if (! $this->isConfigured()) {
            throw new RuntimeException('Collega GitHub dalle Impostazioni per visualizzare le repository.');
        }

        if ($fresh) {
            Cache::forget('web-repositories:'.self::ORGANIZATION);
        }

        return Cache::remember('web-repositories:'.self::ORGANIZATION, now()->addMinutes(5), function (): array {
            $client = $this->client();
            $repositories = [];

            for ($page = 1; $page <= 50; $page++) {
                $items = $client->get('https://api.github.com/orgs/'.self::ORGANIZATION.'/repos', [
                    'type' => 'all',
                    'per_page' => 100,
                    'page' => $page,
                ])->throw()->json();

                if (! is_array($items)) {
                    throw new RuntimeException('Risposta GitHub non valida.');
                }

                foreach ($items as $repo) {
                    if (($repo['custom_properties'][self::PROPERTY] ?? null) !== self::VALUE) {
                        continue;
                    }

                    $name = $repo['name'] ?? '';
                    if (! is_string($name) || ! preg_match('/^[A-Za-z0-9._-]+$/', $name)) {
                        continue;
                    }

                    $repositories[] = [
                        'name' => $name,
                        'description' => $repo['description'] ?? null,
                        'url' => 'https://github.com/'.self::ORGANIZATION.'/'.$name,
                        'private' => (bool) ($repo['private'] ?? false),
                        'language' => $repo['language'] ?? null,
                        'updated_at' => $repo['updated_at'] ?? null,
                    ];
                }

                if (count($items) < 100) {
                    break;
                }
            }

            usort($repositories, fn ($a, $b) => strcasecmp($a['name'], $b['name']));

            return $repositories;
        });
    }

    public function downloadArchive(string $repository): string
    {
        if (! preg_match('/^[A-Za-z0-9._-]+$/', $repository)
            || ! collect($this->repositories(true))->contains(fn ($item) => $item['name'] === $repository)) {
            throw new ModelNotFoundException();
        }

        $redirect = $this->client()
            ->withOptions(['allow_redirects' => false])
            ->get('https://api.github.com/repos/'.self::ORGANIZATION.'/'.$repository.'/zipball');

        if ($redirect->status() === 403) {
            throw new RuntimeException('Il token GitHub deve avere anche il permesso Contents: Read-only per scaricare gli ZIP.');
        }

        if ($redirect->status() !== 302) {
            throw new RuntimeException('Archivio GitHub non disponibile.');
        }

        $location = $redirect->header('Location');
        $parts = parse_url($location ?: '');
        if (($parts['scheme'] ?? null) !== 'https' || ($parts['host'] ?? null) !== 'codeload.github.com'
            || isset($parts['user']) || isset($parts['pass']) || isset($parts['port'])) {
            throw new RuntimeException('Indirizzo di download GitHub non valido.');
        }

        $path = tempnam(sys_get_temp_dir(), 'centro-repo-');
        if ($path === false) {
            throw new RuntimeException('Impossibile preparare il download.');
        }

        try {
            Http::withOptions([
                'sink' => $path,
                'progress' => static function ($total, $downloaded): void {
                    if ($downloaded > 250 * 1024 * 1024) {
                        throw new RuntimeException('Archivio oltre il limite di 250 MB.');
                    }
                },
            ])->connectTimeout(8)->timeout(120)->get($location)->throw();

            if (filesize($path) > 250 * 1024 * 1024) {
                throw new RuntimeException('Archivio oltre il limite di 250 MB.');
            }

            $handle = fopen($path, 'rb');
            $signature = $handle ? fread($handle, 4) : null;
            if ($handle) {
                fclose($handle);
            }
            if (! in_array($signature, ["PK\x03\x04", "PK\x05\x06"], true)) {
                throw new RuntimeException('Il file ricevuto da GitHub non è uno ZIP valido.');
            }

            return $path;
        } catch (Throwable $exception) {
            @unlink($path);
            throw $exception;
        }
    }

    private function client(): PendingRequest
    {
        $encrypted = DB::table('github_settings')->value('encrypted_token');

        return Http::acceptJson()
            ->withToken(Crypt::decryptString($encrypted))
            ->withHeaders(['X-GitHub-Api-Version' => '2022-11-28'])
            ->connectTimeout(8)
            ->timeout(20);
    }
}
