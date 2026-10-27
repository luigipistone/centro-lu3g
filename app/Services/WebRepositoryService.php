<?php

namespace App\Services;

use Illuminate\Http\Client\PendingRequest;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use RuntimeException;

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
