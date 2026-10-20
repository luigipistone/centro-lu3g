<?php

namespace App\Services;

use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

class EmployeeDossierExpiryService
{
    public function __construct(private readonly CentroNotificationService $notifications) {}

    public function sendDueReminders(): int
    {
        $today = now('Europe/Rome')->startOfDay();
        $superadminIds = DB::table('user_roles')->where('role', 'superadmin')->pluck('user_id')->all();
        $sent = 0;

        DB::table('employee_dossier_items')
            ->join('users', 'users.id', '=', 'employee_dossier_items.user_id')
            ->whereNull('employee_dossier_items.replaced_at')
            ->whereBetween('employee_dossier_items.expires_at', [
                $today->copy()->subDays(7)->toDateString(),
                $today->copy()->addDays(30)->toDateString(),
            ])
            ->select('employee_dossier_items.*', 'users.name as employee_name')
            ->orderBy('employee_dossier_items.id')
            ->chunk(100, function ($items) use ($today, $superadminIds, &$sent) {
                foreach ($items as $item) {
                    $days = $today->diffInDays($item->expires_at, false);
                    $stage = $days <= 0 ? 'expired' : 'expiring';
                    $date = Carbon::parse($item->expires_at)->format('d/m/Y');
                    $document = $item->classification === 'medical' || $item->classification === 'admin'
                        ? 'Un documento riservato'
                        : 'Il documento "'.$item->title.'"';
                    $recipients = $superadminIds;
                    if ($item->classification !== 'admin') {
                        $recipients[] = $item->user_id;
                    }

                    foreach (array_unique($recipients) as $recipientId) {
                        $claim = [
                            'dossier_item_id' => $item->id,
                            'user_id' => $recipientId,
                            'stage' => $stage,
                        ];
                        if (! DB::table('employee_dossier_expiry_reminders')->insertOrIgnore($claim + [
                            'created_at' => now(), 'updated_at' => now(),
                        ])) {
                            continue;
                        }

                        $message = $document.' del fascicolo di '.$item->employee_name
                            .($stage === 'expired' ? ' è scaduto il ' : ' scade il ').$date.'.';
                        try {
                            $this->notifications->notifyUsers([$recipientId], null, 'document_dossier_'.$stage, $message);
                            $sent++;
                        } catch (\Throwable $exception) {
                            DB::table('employee_dossier_expiry_reminders')->where($claim)->delete();
                            throw $exception;
                        }
                    }
                }
            });

        return $sent;
    }
}
