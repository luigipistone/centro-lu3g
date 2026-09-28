<?php

namespace App\Services;

use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class ScheduledCompanyMessageService
{
    public function publishDue(): int
    {
        $now = now('Europe/Rome');
        $ids = DB::table('company_message_schedules')->where('active', true)
            ->where('next_run_at', '<=', $now->toDateTimeString())->pluck('id');
        $published = 0;

        foreach ($ids as $id) {
            $result = DB::transaction(function () use ($id, $now) {
                $schedule = DB::table('company_message_schedules')->where('id', $id)->lockForUpdate()->first();
                if (! $schedule || ! $schedule->active || $schedule->next_run_at > $now->toDateTimeString()) {
                    return null;
                }
                if ($schedule->ends_on && $schedule->ends_on < $now->toDateString()) {
                    DB::table('company_message_schedules')->where('id', $id)->update(['active' => false, 'updated_at' => now()]);

                    return null;
                }
                $messageId = (string) Str::uuid();
                $sentAt = now();
                DB::table('company_messages')->insert([
                    'id' => $messageId, 'title' => $schedule->title, 'body' => $schedule->body,
                    'audience' => $schedule->audience, 'created_by' => $schedule->created_by,
                    'created_at' => $sentAt, 'updated_at' => $sentAt,
                ]);

                $userIds = DB::table('users')->whereIn('id', array_unique(json_decode($schedule->user_ids ?: '[]', true) ?: []))->pluck('id');
                foreach ($userIds as $userId) {
                    DB::table('company_message_user')->insert([
                        'id' => (string) Str::uuid(), 'company_message_id' => $messageId,
                        'user_id' => $userId, 'created_at' => $sentAt, 'updated_at' => $sentAt,
                    ]);
                }
                $groupIds = DB::table('document_groups')->whereIn('id', array_unique(json_decode($schedule->group_ids ?: '[]', true) ?: []))->pluck('id');
                foreach ($groupIds as $groupId) {
                    DB::table('company_message_group')->insert([
                        'id' => (string) Str::uuid(), 'company_message_id' => $messageId,
                        'document_group_id' => $groupId, 'created_at' => $sentAt, 'updated_at' => $sentAt,
                    ]);
                }

                $nextRun = Carbon::parse($schedule->next_run_at, 'Europe/Rome');
                do {
                    $nextRun = match ($schedule->recurrence) {
                        'daily' => $nextRun->addDay(),
                        'weekly' => $nextRun->addWeek(),
                        'monthly' => $nextRun->addMonthNoOverflow(),
                        default => null,
                    };
                } while ($nextRun && $nextRun->lte($now));

                DB::table('company_message_schedules')->where('id', $id)->update([
                    'active' => $nextRun && (! $schedule->ends_on || $nextRun->toDateString() <= $schedule->ends_on),
                    'next_run_at' => $nextRun?->toDateTimeString() ?? $schedule->next_run_at,
                    'updated_at' => $sentAt,
                ]);

                return [$messageId, $schedule->created_by, $schedule->title];
            });

            if ($result) {
                [$messageId, $authorId, $title] = $result;
                $recipients = $this->recipientIds($messageId);
                foreach ($recipients as $userId) {
                    DB::table('company_message_reads')->insertOrIgnore([
                        'id' => (string) Str::uuid(), 'company_message_id' => $messageId,
                        'user_id' => $userId, 'created_at' => now(), 'updated_at' => now(),
                    ]);
                }
                app(CentroNotificationService::class)->notifyUsers($recipients, $authorId, 'company_message_created',
                    'È stato pubblicato il messaggio "'.$title.'".', null, null, $messageId);
                $published++;
            }
        }

        return $published;
    }

    private function recipientIds(string $messageId)
    {
        $message = DB::table('company_messages')->where('id', $messageId)->first(['audience']);
        if ($message->audience === 'all') {
            return DB::table('users')->where('account_status', 'active')->pluck('id');
        }
        $userIds = DB::table('company_message_user')->where('company_message_id', $messageId)->pluck('user_id');
        $groupIds = DB::table('company_message_group')->where('company_message_id', $messageId)->pluck('document_group_id');

        return $userIds->merge(DB::table('document_group_user')->whereIn('document_group_id', $groupIds)->pluck('user_id'))
            ->unique()->values();
    }
}
