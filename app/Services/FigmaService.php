<?php

namespace App\Services;

use Illuminate\Http\Client\PendingRequest;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use RuntimeException;

class FigmaService
{
    public function projects(): array
    {
        $settings = $this->settings();

        if (blank($settings->team_id)) {
            throw new RuntimeException('Inserisci il Team ID Figma nelle impostazioni.');
        }

        return $this->client($settings)
            ->get("https://api.figma.com/v1/teams/{$settings->team_id}/projects")
            ->throw()
            ->json('projects', []);
    }

    public function files(string $projectId): array
    {
        return $this->client($this->settings())
            ->get("https://api.figma.com/v1/projects/{$projectId}/files")
            ->throw()
            ->json('files', []);
    }

    private function settings(): object
    {
        $settings = DB::table('figma_settings')->first();

        if (! $settings || blank($settings->encrypted_token)) {
            throw new RuntimeException('Configura il token Figma nelle impostazioni.');
        }

        return $settings;
    }

    private function client(object $settings): PendingRequest
    {
        return Http::acceptJson()
            ->withHeaders(['X-Figma-Token' => Crypt::decryptString($settings->encrypted_token)])
            ->connectTimeout(8)
            ->timeout(20)
            ->retry(2, 250);
    }
}
