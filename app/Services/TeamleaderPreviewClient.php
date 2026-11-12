<?php

namespace App\Services;

use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use RuntimeException;

class TeamleaderPreviewClient
{
    // Call only while holding the integration lock: refresh tokens rotate on every use.
    public function exchange(object $settings, array $grant): void
    {
        $response = Http::asForm()->connectTimeout(10)->timeout(25)->post('https://focus.teamleader.eu/oauth2/access_token', [
            'client_id' => $settings->client_id, 'client_secret' => Crypt::decryptString($settings->client_secret),
        ] + $grant);
        $data = $response->json();
        if (! $response->successful() || ! is_string($data['access_token'] ?? null) || ! is_string($data['refresh_token'] ?? null) || ! is_numeric($data['expires_in'] ?? null)) {
            throw new RuntimeException('Autorizzazione Teamleader non riuscita. Ricollega il servizio.');
        }
        DB::table('teamleader_settings')->where('id', 1)->update([
            'access_token' => Crypt::encryptString($data['access_token']), 'refresh_token' => Crypt::encryptString($data['refresh_token']),
            'expires_at' => now()->addSeconds((int) $data['expires_in']), 'updated_at' => now(),
        ]);
    }

    public function read(string $method, array $payload): array
    {
        if (! in_array($method, ['meetings.list', 'users.info'], true)) throw new RuntimeException('Operazione non consentita.');
        $s = DB::table('teamleader_settings')->find(1);
        if (! $s?->refresh_token) throw new RuntimeException('Collega prima Teamleader.');
        if (! $s->access_token || ! $s->expires_at || now()->addMinute()->gte($s->expires_at)) {
            $this->exchange($s, ['grant_type' => 'refresh_token', 'refresh_token' => Crypt::decryptString($s->refresh_token)]);
            $s = DB::table('teamleader_settings')->find(1);
        }
        $response = Http::withToken(Crypt::decryptString($s->access_token))->connectTimeout(10)->timeout(25)->post('https://api.focus.teamleader.eu/'.$method, $payload);
        if (! $response->successful() || ! is_array($response->json('data'))) {
            throw new RuntimeException('Lettura Teamleader non riuscita (HTTP '.$response->status().'). Verifica autorizzazioni o riprova.');
        }
        return $response->json('data');
    }
}
