<?php

namespace App\Services;

use Illuminate\Support\Str;

class NotificationDestination
{
    public static function url(
        string $type,
        ?string $taskId = null,
        ?string $documentId = null,
        ?string $messageId = null,
        ?string $role = null,
    ): string {
        if ($type === 'task_deleted') {
            return route('tasks.index');
        }
        if ($taskId) {
            return route('tasks.show', $taskId);
        }
        if ($documentId) {
            return route('documents.show', $documentId);
        }
        if ($messageId) {
            return route('document-messages.show', $messageId);
        }

        if (Str::startsWith($type, 'task_')) {
            return route('tasks.index');
        }
        if (Str::startsWith($type, 'project_')) {
            return route('projects.index');
        }
        if (Str::startsWith($type, 'absence_')) {
            return in_array($role, ['admin', 'superadmin'], true) ? route('absences.index') : route('profile.edit');
        }
        if (Str::startsWith($type, 'document_dossier_')) {
            return $role === 'superadmin' ? route('users.index') : route('profile.edit');
        }
        if (Str::startsWith($type, ['company_document_', 'company_message_', 'document_'])) {
            return route('documents.index');
        }
        if (Str::startsWith($type, 'password_')) {
            return route('passwords.index');
        }
        if (Str::startsWith($type, ['profile_', 'account_archive_'])) {
            return $type === 'account_archive_requested' && $role === 'superadmin'
                ? route('users.index') : route('profile.edit');
        }
        if (Str::startsWith($type, 'figma_')) {
            return route('settings.index');
        }

        return route('notifications.index');
    }
}
