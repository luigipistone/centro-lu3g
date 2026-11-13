<?php

namespace App\Services;

use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;
use RuntimeException;

class GmailRequests
{
    public const SCOPE = 'https://www.googleapis.com/auth/gmail.readonly';
    private ?string $token = null;

    public static function limited(\Throwable $e): bool
    {
        return $e instanceof \Illuminate\Http\Client\RequestException && ($e->response->status() === 429
            || in_array($e->response->json('error.errors.0.reason'), ['rateLimitExceeded', 'userRateLimitExceeded', 'quotaExceeded'], true));
    }

    public static function coolingDown(string $id): bool
    {
        return Cache::has('gmail:cooldown:'.$id);
    }

    public static function pause(string $id): void
    {
        Cache::put('gmail:cooldown:'.$id, true, now()->addMinutes(5));
    }

    public function exchange(array $grant): array
    {
        $s = DB::table('gmail_settings')->first();
        if (! $s) throw new RuntimeException('Configura Gmail.');
        return Http::asForm()->connectTimeout(10)->timeout(30)->post('https://oauth2.googleapis.com/token', $grant + [
            'client_id' => $s->client_id, 'client_secret' => Crypt::decryptString($s->client_secret),
        ])->throw()->json();
    }

    public function get(object $connection, string $path, array $params = []): array
    {
        if (! $this->token) $this->token = $this->exchange(['grant_type' => 'refresh_token', 'refresh_token' => Crypt::decryptString($connection->refresh_token)])['access_token'];
        return Http::withToken($this->token)->connectTimeout(10)->timeout(60)
            ->get('https://gmail.googleapis.com/gmail/v1/users/me/'.$path, $params)->throw()->json();
    }

    public function sync(string $id): void
    {
        if (self::coolingDown($id)) return;
        $lock = Cache::lock('gmail:'.$id, 1500);
        if (! $lock->get()) return;
        try {
            $c = DB::table('gmail_connections')->find($id);
            if (! $c || ! $c->enabled || ! $c->refresh_token || ! $c->label_id) return;
            $role = DB::table('user_roles')->where('user_id', $c->user_id)->value('role');
            if (! in_array($role, ['admin', 'superadmin'], true) || ! DB::table('users')->where('id', $c->user_id)->where('account_status', 'active')->exists()
                || ! app(RolePermissionService::class)->allows($role ?? '', 'requests.manage')) return;
            // Re-list the label, including old messages labelled today. Message IDs make retries idempotent.
            $page = null; $seen = []; $labelledThreads = []; $started = microtime(true);
            do {
                if (microtime(true) - $started > 1200) throw new RuntimeException('Lettura da riprendere.');
                // A PHP array becomes labelIds[0], which Gmail ignores. One label uses the scalar query key.
                $result = $this->get($c, 'messages', ['labelIds' => $c->label_id, 'maxResults' => 100] + ($page ? ['pageToken' => $page] : []));
                foreach ($result['messages'] ?? [] as $item) {
                    if (microtime(true) - $started > 1200) throw new RuntimeException('Lettura da riprendere.');
                    $existing = DB::table('mail_request_messages as m')->join('mail_requests as r', 'r.id', '=', 'm.request_id')
                        ->where('m.connection_id', $id)->where('m.gmail_id', $item['id'])->value('r.thread_id');
                    if ($existing) {
                        $labelledThreads[$existing] = true;
                        continue;
                    }
                    $message = $this->get($c, 'messages/'.rawurlencode($item['id']), ['format' => 'full']);
                    if (! in_array($c->label_id, $message['labelIds'] ?? [], true)) continue;
                    $labelledThreads[$message['threadId']] = true;
                    $this->ingest($c, $message);
                }
                $page = $result['nextPageToken'] ?? null;
                if ($page && isset($seen[$page])) throw new RuntimeException('Paginazione non valida.');
                if ($page) $seen[$page] = true;
            } while ($page);
            // Follow replies only while at least one message in the conversation retains the label.
            foreach (array_keys($labelledThreads) as $threadId) {
                if (microtime(true) - $started > 1200) throw new RuntimeException('Lettura da riprendere.');
                try { $thread = $this->get($c, 'threads/'.rawurlencode($threadId), ['format' => 'full']); }
                catch (\Illuminate\Http\Client\RequestException $e) {
                    if ($e->response->status() === 404) continue;
                    throw $e;
                }
                $messages = $thread['messages'] ?? [];
                if (! collect($messages)->contains(fn ($message) => in_array($c->label_id, $message['labelIds'] ?? [], true))) continue;
                foreach ($messages as $message) $this->ingest($c, $message);
            }
            DB::table('gmail_connections')->where('id', $id)->update(['last_synced_at' => now(), 'last_error' => null, 'requested_at' => null]);
        } catch (\Throwable $e) {
            if (self::limited($e)) self::pause($id);
            DB::table('gmail_connections')->where('id', $id)->update(['last_synced_at' => now(), 'last_error' => self::limited($e)
                ? 'Google ha limitato temporaneamente le richieste. Il Centro riprovera automaticamente fra 5 minuti. Non serve ricollegare Gmail; le email importate sono conservate.'
                : 'Importazione non completata. Verifica collegamento ed etichetta; i messaggi acquisiti sono conservati e il controllo verra ripetuto.']);
        } finally { $lock->release(); }
    }

    public static function decode(string $value): string
    {
        $decoded = base64_decode(strtr($value, '-_', '+/'), true);
        if ($decoded === false) throw new RuntimeException('Contenuto email non valido.');
        return $decoded;
    }

    public function ingest(object $connection, array $message): void
    {
        if (empty($message['id']) || empty($message['threadId']) || ! isset($message['payload'])) throw new RuntimeException('Email incompleta.');
        $headers = [];
        foreach ($message['payload']['headers'] ?? [] as $header) $headers[strtolower($header['name'])] = $header['value'];
        $subject = Str::limit($headers['subject'] ?? '(Senza oggetto)', 255, '');
        $from = $headers['from'] ?? '';
        preg_match('/[A-Z0-9.!#$%&\x27*+\/=?^_`{|}~-]+@[A-Z0-9.-]+\.[A-Z]{2,}/i', $from, $email);
        $sender = strtolower($email[0] ?? '');
        $hash = hash('sha256', trim($headers['message-id'] ?? ($connection->email.':'.$message['id'])));
        $plain = []; $html = []; $attachments = [];
        $walk = function ($part) use (&$walk, &$plain, &$html, &$attachments) {
            if (! empty($part['filename'])) {
                $attachments[] = ['name' => $part['filename'], 'id' => $part['body']['attachmentId'] ?? null, 'part' => $part['partId'] ?? '', 'size' => $part['body']['size'] ?? 0];
                return;
            }
            if (isset($part['body']['data'])) {
                $body = self::decode($part['body']['data']);
                foreach ($part['headers'] ?? [] as $header) {
                    if (strtolower($header['name']) === 'content-type' && preg_match('/charset=["\x27]?([^;"\x27\s]+)/i', $header['value'], $charset)) {
                        try { $body = mb_convert_encoding($body, 'UTF-8', $charset[1]); } catch (\ValueError) { /* Fall back to valid UTF-8 below. */ }
                    }
                }
                $body = mb_convert_encoding($body, 'UTF-8', 'UTF-8');
                if (($part['mimeType'] ?? '') === 'text/plain') $plain[] = $body;
                if (($part['mimeType'] ?? '') === 'text/html') $html[] = html_entity_decode(strip_tags(preg_replace('/<(script|style)\b[^>]*>.*?<\/\1>/is', '', preg_replace('/<\/(p|div|li|br)>/i', "\n", $body))), ENT_QUOTES | ENT_HTML5, 'UTF-8');
            }
            foreach ($part['parts'] ?? [] as $child) $walk($child);
        };
        $walk($message['payload']);
        $body = implode("\n\n", $plain ?: $html);
        DB::transaction(function () use ($connection, $message, $subject, $sender, $hash, $body, $attachments) {
            if (DB::table('mail_request_messages')->where('connection_id', $connection->id)->where('gmail_id', $message['id'])->exists()) return;
            $request = DB::table('mail_requests')->where('connection_id', $connection->id)->where('thread_id', $message['threadId'])->lockForUpdate()->first();
            $id = $request->id ?? (string) Str::uuid();
            if (! $request) {
                $clients = $sender ? DB::table('clients')->where('active', true)->where(function ($q) use ($sender) {
                    $q->whereRaw('LOWER(email) = ?', [$sender])->orWhereIn('id', DB::table('client_contacts')->select('client_id')->whereRaw('LOWER(email) = ?', [$sender]));
                })->pluck('id') : collect();
                $client = $clients->count() === 1 ? $clients->first() : null;
                $projects = $client ? DB::table('projects')->where('client_id', $client)->where('status', '!=', 'archived')->pluck('id') : collect();
                $duplicate = DB::table('mail_request_messages')->where('message_hash', $hash)->value('request_id');
                DB::table('mail_requests')->insert(['id' => $id, 'connection_id' => $connection->id, 'thread_id' => $message['threadId'],
                    'subject' => $subject, 'sender' => $sender, 'suggested_client_id' => $client,
                    'suggested_project_id' => $projects->count() === 1 ? $projects->first() : null,
                    'duplicate_of' => $duplicate, 'created_at' => now(), 'updated_at' => now()]);
            } else {
                DB::table('mail_requests')->where('id', $id)->update(['version' => $request->version + 1, 'has_updates' => true, 'updated_at' => now()]);
            }
            DB::table('mail_request_messages')->insert(['id' => (string) Str::uuid(), 'request_id' => $id, 'connection_id' => $connection->id,
                'gmail_id' => $message['id'], 'message_hash' => $hash, 'sender' => $sender, 'subject' => $subject, 'body' => $body,
                'attachments' => json_encode($attachments, JSON_THROW_ON_ERROR), 'received_at' => isset($message['internalDate']) ? \Carbon\CarbonImmutable::createFromTimestampMs($message['internalDate']) : null,
                'created_at' => now(), 'updated_at' => now()]);
        });
    }
}
