<?php

namespace App\Services;

use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Str;
use Minishlink\WebPush\Subscription;
use Minishlink\WebPush\WebPush;

class CentroNotificationService
{
    public const CATEGORIES = ['tasks', 'projects', 'absences', 'documents', 'system'];

    public function notifyUsers(iterable $userIds, ?string $actorId, string $type, string $message, ?string $taskId = null, ?string $companyDocumentId = null): void
    {
        $now = now();
        $category = $this->categoryForType($type);

        foreach (collect($userIds)->filter()->unique()->values() as $userId) {
            $userId = (string) $userId;
            $preferences = $this->preferencesForUser($userId, $category);
            $notificationId = (string) str()->uuid();
            $externalAllowed = Cache::add(
                'centro-notification-external:'.md5(json_encode([
                    $userId,
                    $actorId,
                    $type,
                    $taskId,
                    $companyDocumentId,
                    $this->shouldCoalesceNotification($type, $taskId) ? null : $message,
                ], JSON_THROW_ON_ERROR)),
                true,
                now()->addMinutes(2),
            );

            if ($preferences['in_app']) {
                $existingNotification = DB::table('notifications')
                    ->where('user_id', $userId)
                    ->where('type', $type)
                    ->where('read', false)
                    ->whereNull('archived_at')
                    ->where('created_at', '>=', $now->copy()->subMinutes(2))
                    ->when($actorId, fn ($query) => $query->where('actor_id', $actorId), fn ($query) => $query->whereNull('actor_id'))
                    ->when($taskId, fn ($query) => $query->where('task_id', $taskId), fn ($query) => $query->whereNull('task_id'))
                    ->when($companyDocumentId, fn ($query) => $query->where('company_document_id', $companyDocumentId), fn ($query) => $query->whereNull('company_document_id'))
                    ->when(! $this->shouldCoalesceNotification($type, $taskId), fn ($query) => $query->where('message', $message))
                    ->latest('created_at')
                    ->first(['id']);

                if ($existingNotification) {
                    $notificationId = (string) $existingNotification->id;
                    DB::table('notifications')
                        ->where('id', $notificationId)
                        ->update([
                            'message' => $message,
                            'created_at' => $now,
                            'updated_at' => $now,
                        ]);
                } else {
                    DB::table('notifications')->insert([
                        'id' => $notificationId,
                        'user_id' => $userId,
                        'actor_id' => $actorId,
                        'task_id' => $taskId,
                        'company_document_id' => $companyDocumentId,
                        'type' => $type,
                        'message' => $message,
                        'read' => false,
                        'created_at' => $now,
                        'updated_at' => $now,
                    ]);
                }
            }

            if ($preferences['browser'] && $externalAllowed) {
                $this->sendBrowserPushNotification($userId, $notificationId, $message, $taskId, $companyDocumentId);
            }

            if ($preferences['mail'] && $externalAllowed) {
                $this->sendMailNotification($userId, $message, $taskId, $companyDocumentId);
            }
        }
    }

    private function preferencesForUser(string $userId, string $category): array
    {
        $row = DB::table('notification_preferences')
            ->where('user_id', $userId)
            ->where('category', $category)
            ->first(['in_app', 'browser', 'mail']);

        return [
            'in_app' => $row ? (bool) $row->in_app : true,
            'browser' => $row ? (bool) $row->browser : true,
            'mail' => $row ? (bool) $row->mail : false,
        ];
    }

    private function categoryForType(string $type): string
    {
        if (Str::startsWith($type, ['task_', 'task'])) return 'tasks';
        if (Str::startsWith($type, ['project_', 'project'])) return 'projects';
        if (Str::startsWith($type, ['absence_', 'absence'])) return 'absences';
        if (Str::startsWith($type, ['company_document', 'document'])) return 'documents';

        return 'system';
    }

    private function shouldCoalesceNotification(string $type, ?string $taskId): bool
    {
        return $taskId !== null && in_array($type, ['task_updated'], true);
    }

    private function sendBrowserPushNotification(string $userId, string $notificationId, string $message, ?string $taskId = null, ?string $companyDocumentId = null): void
    {
        $publicKey = config('services.webpush.public_key');
        $privateKey = config('services.webpush.private_key');

        if (! $publicKey || ! $privateKey) {
            return;
        }

        $subscriptions = DB::table('push_subscriptions')
            ->where('user_id', $userId)
            ->get();

        if ($subscriptions->isEmpty()) {
            return;
        }

        $webPush = new WebPush([
            'VAPID' => [
                'subject' => config('services.webpush.subject') ?: config('app.url'),
                'publicKey' => $publicKey,
                'privateKey' => $privateKey,
            ],
        ]);

        $payload = json_encode([
            'id' => $notificationId,
            'title' => 'Il Centro',
            'body' => $message,
            'tag' => $notificationId,
            'url' => $taskId ? route('tasks.show', $taskId) : ($companyDocumentId ? route('documents.show', $companyDocumentId) : route('notifications.index')),
        ], JSON_THROW_ON_ERROR);

        foreach ($subscriptions as $subscription) {
            $webPush->queueNotification(
                Subscription::create([
                    'endpoint' => $subscription->endpoint,
                    'publicKey' => $subscription->public_key,
                    'authToken' => $subscription->auth_token,
                    'contentEncoding' => $subscription->content_encoding ?: 'aes128gcm',
                ]),
                $payload,
            );
        }

        foreach ($webPush->flush() as $report) {
            if ($report->isSuccess()) {
                continue;
            }

            Log::warning('Invio notifica push browser non riuscito.', [
                'user_id' => $userId,
                'notification_id' => $notificationId,
                'endpoint' => $report->getEndpoint(),
                'reason' => $report->getReason(),
            ]);

            if ($report->isSubscriptionExpired()) {
                DB::table('push_subscriptions')
                    ->where('endpoint', $report->getEndpoint())
                    ->delete();
            }
        }
    }

    private function sendMailNotification(string $userId, string $message, ?string $taskId = null, ?string $companyDocumentId = null): void
    {
        $settings = DB::table('email_settings')->first();
        if (! $settings || ! $settings->smtp_enabled || ! $settings->smtp_host || ! $settings->smtp_from_email) {
            return;
        }

        $user = DB::table('users')->where('id', $userId)->first(['email', 'name']);
        if (! $user?->email) {
            return;
        }

        Config::set('mail.default', 'smtp');
        Config::set('mail.mailers.smtp.host', $settings->smtp_host);
        Config::set('mail.mailers.smtp.port', $settings->smtp_port ?: 587);
        Config::set('mail.mailers.smtp.username', $settings->smtp_username);
        Config::set('mail.mailers.smtp.password', $settings->smtp_password);
        Config::set('mail.mailers.smtp.encryption', $settings->smtp_secure ? 'tls' : null);
        Config::set('mail.from.address', $settings->smtp_from_email);
        Config::set('mail.from.name', $settings->smtp_from_name ?: 'Il Centro');

        $url = $taskId ? route('tasks.show', $taskId) : ($companyDocumentId ? route('documents.show', $companyDocumentId) : route('notifications.index'));

        try {
            Mail::html($this->notificationEmailHtml($message, $url, $user->name ?: null), function ($mail) use ($user, $settings) {
                $mail->to($user->email, $user->name ?: null)
                    ->subject('Nuova notifica - Il Centro');

                if ($settings->smtp_reply_to) {
                    $mail->replyTo($settings->smtp_reply_to);
                }
            });
        } catch (\Throwable $exception) {
            Log::warning('Invio notifica email non riuscito.', [
                'user_id' => $userId,
                'reason' => $exception->getMessage(),
            ]);
        }
    }

    private function notificationEmailHtml(string $message, string $url, ?string $userName = null): string
    {
        $appUrl = rtrim(config('app.url'), '/');
        $logoUrl = $appUrl.'/brand/logo-gestionale-webapp.svg';
        $safeMessage = nl2br(e($message));
        $safeUrl = e($url);
        $safeName = $userName ? e($userName) : 'ciao';
        $preview = e(Str::limit(strip_tags($message), 110));

        return <<<HTML
<!doctype html>
<html lang="it">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="color-scheme" content="light">
    <title>Nuova notifica - Il Centro</title>
    <style>
        @media only screen and (max-width: 620px) {
            .container { width: 100% !important; }
            .card { border-radius: 22px !important; }
            .content { padding: 24px !important; }
            .button { display: block !important; width: 100% !important; box-sizing: border-box !important; }
        }
    </style>
</head>
<body style="margin:0;padding:0;background:#f3f8ff;font-family:-apple-system,BlinkMacSystemFont,'Segoe UI',Roboto,Arial,sans-serif;color:#172033;">
    <div style="display:none;max-height:0;overflow:hidden;opacity:0;color:transparent;">{$preview}</div>
    <table role="presentation" width="100%" cellspacing="0" cellpadding="0" style="background:#f3f8ff;margin:0;padding:28px 14px;">
        <tr>
            <td align="center">
                <table role="presentation" class="container" width="600" cellspacing="0" cellpadding="0" style="width:600px;max-width:600px;">
                    <tr>
                        <td style="padding:0 0 16px 0;">
                            <table role="presentation" width="100%" cellspacing="0" cellpadding="0">
                                <tr>
                                    <td style="vertical-align:middle;">
                                        <span style="display:inline-block;width:44px;height:44px;border-radius:16px;background:#ffffff;border:1px solid #dbeafe;text-align:center;vertical-align:middle;box-shadow:0 10px 28px rgba(11,110,243,0.12);">
                                            <img src="{$logoUrl}" width="30" height="30" alt="Il Centro" style="display:block;margin:7px auto;border:0;outline:none;text-decoration:none;">
                                        </span>
                                        <span style="display:inline-block;margin-left:10px;vertical-align:middle;font-size:18px;font-weight:800;color:#0f172a;letter-spacing:0;">Il Centro</span>
                                    </td>
                                </tr>
                            </table>
                        </td>
                    </tr>
                    <tr>
                        <td class="card" style="overflow:hidden;border-radius:28px;background:#ffffff;border:1px solid #dbeafe;box-shadow:0 24px 70px rgba(28,42,73,0.12);">
                            <table role="presentation" width="100%" cellspacing="0" cellpadding="0">
                                <tr>
                                    <td style="height:5px;background:#0b6ef3;font-size:0;line-height:0;">&nbsp;</td>
                                </tr>
                                <tr>
                                    <td class="content" style="padding:32px;">
                                        <p style="margin:0 0 8px 0;font-size:13px;font-weight:700;text-transform:uppercase;letter-spacing:.08em;color:#0b6ef3;">Nuova notifica</p>
                                        <h1 style="margin:0 0 16px 0;font-size:26px;line-height:1.18;font-weight:800;color:#101828;">Ciao {$safeName}</h1>
                                        <div style="margin:0 0 24px 0;padding:18px 18px;border-radius:18px;background:#f8fbff;border:1px solid #e6f0ff;font-size:16px;line-height:1.55;color:#263247;">
                                            {$safeMessage}
                                        </div>
                                        <a href="{$safeUrl}" class="button" style="display:inline-block;border-radius:16px;background:#0b6ef3;color:#ffffff;text-decoration:none;font-size:15px;font-weight:800;padding:14px 22px;box-shadow:0 14px 28px rgba(11,110,243,0.24);">Apri notifica</a>
                                        <p style="margin:22px 0 0 0;font-size:12px;line-height:1.5;color:#667085;">Se il pulsante non funziona, copia e incolla questo link nel browser:<br><a href="{$safeUrl}" style="color:#0b6ef3;text-decoration:none;word-break:break-all;">{$safeUrl}</a></p>
                                    </td>
                                </tr>
                            </table>
                        </td>
                    </tr>
                    <tr>
                        <td style="padding:18px 6px 0 6px;text-align:center;font-size:12px;line-height:1.5;color:#7a8699;">
                            Ricevi questa email in base alle preferenze notifiche del tuo profilo.
                        </td>
                    </tr>
                </table>
            </td>
        </tr>
    </table>
</body>
</html>
HTML;
    }
}
