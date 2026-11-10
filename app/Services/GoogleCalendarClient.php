<?php

namespace App\Services;

use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\Http;
use RuntimeException;

class GoogleCalendarClient
{
    private ?string $accessToken = null;
    private float $startedAt;
    public function __construct() { $this->startedAt = microtime(true); }
    public const SCOPES = 'https://www.googleapis.com/auth/calendar.events.readonly https://www.googleapis.com/auth/calendar.calendarlist.readonly';

    public function exchange(object $settings, array $grant): array
    {
        return Http::asForm()->connectTimeout(10)->timeout(30)->post('https://oauth2.googleapis.com/token', array_merge([
            'client_id' => $settings->client_id,
            'client_secret' => Crypt::decryptString($settings->client_secret),
        ], $grant))->throw()->json();
    }

    public function request(object $settings, string $method, string $path, array $data = []): array
    {
        if (microtime(true) - $this->startedAt > 1200) throw new RuntimeException('Tempo di lettura Google superato.');
        if (! $this->accessToken) {
            $this->accessToken = $this->exchange($settings, [
                'grant_type' => 'refresh_token', 'refresh_token' => Crypt::decryptString($settings->refresh_token),
            ])['access_token'];
        }
        $response = Http::withToken($this->accessToken)->connectTimeout(10)->timeout(60)
            ->send($method, 'https://www.googleapis.com/calendar/v3/'.$path, [$method === 'GET' ? 'query' : 'json' => $data]);
        $response->throw();
        return $response->json() ?? [];
    }

    public function pages(object $settings, string $path, array $params = []): array
    {
        $items = []; $seen = []; $token = null;
        do {
            $page = $this->request($settings, 'GET', $path, $params + ($token ? ['pageToken' => $token] : []));
            if (! isset($page['items']) || ! is_array($page['items'])) throw new RuntimeException('Risposta Google incompleta.');
            foreach ($page['items'] as $item) {
                if (empty($item['id']) || isset($seen['item:'.$item['id']])) throw new RuntimeException('Elenco Google non coerente.');
                $seen['item:'.$item['id']] = true;
                $items[] = $item;
            }
            $token = $page['nextPageToken'] ?? null;
            if ($token && isset($seen['page:'.$token])) throw new RuntimeException('Paginazione Google non valida.');
            if ($token) $seen['page:'.$token] = true;
        } while ($token);
        return ['items' => $items, 'syncToken' => $page['nextSyncToken'] ?? null];
    }
}
