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
            Mail::raw($message."\n\nApri: ".$url, function ($mail) use ($user, $settings) {
                $mail->to($user->email, $user->name ?: null)
                    ->subject('Il Centro - nuova notifica');

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
}
