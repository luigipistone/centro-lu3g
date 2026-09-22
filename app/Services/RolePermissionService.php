<?php

namespace App\Services;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class RolePermissionService
{
    public const ROLES = ['superadmin', 'admin', 'editor', 'guest'];

    public static function definitions(): array
    {
        return [
            ['group' => 'Generale', 'key' => 'dashboard.view', 'label' => 'Visualizzare dashboard'],
            ['group' => 'Clienti', 'key' => 'clients.view', 'label' => 'Visualizzare clienti'],
            ['group' => 'Clienti', 'key' => 'clients.create', 'label' => 'Creare clienti'],
            ['group' => 'Clienti', 'key' => 'clients.update', 'label' => 'Modificare clienti'],
            ['group' => 'Clienti', 'key' => 'clients.delete', 'label' => 'Eliminare clienti'],
            ['group' => 'Progetti', 'key' => 'projects.view', 'label' => 'Visualizzare progetti'],
            ['group' => 'Progetti', 'key' => 'projects.create', 'label' => 'Creare progetti'],
            ['group' => 'Progetti', 'key' => 'projects.update', 'label' => 'Modificare progetti'],
            ['group' => 'Progetti', 'key' => 'projects.delete', 'label' => 'Eliminare progetti'],
            ['group' => 'Task', 'key' => 'tasks.view', 'label' => 'Visualizzare task'],
            ['group' => 'Task', 'key' => 'tasks.create', 'label' => 'Creare task'],
            ['group' => 'Task', 'key' => 'tasks.update', 'label' => 'Modificare task'],
            ['group' => 'Task', 'key' => 'tasks.delete', 'label' => 'Eliminare task'],
            ['group' => 'Task', 'key' => 'calendar.view', 'label' => 'Visualizzare calendario'],
            ['group' => 'Azienda', 'key' => 'documents.view', 'label' => 'Visualizzare documenti'],
            ['group' => 'Azienda', 'key' => 'documents.manage', 'label' => 'Gestire documenti'],
            ['group' => 'Azienda', 'key' => 'passwords.view', 'label' => 'Visualizzare password autorizzate'],
            ['group' => 'Azienda', 'key' => 'passwords.manage', 'label' => 'Gestire password condivise'],
            ['group' => 'Azienda', 'key' => 'absences.request', 'label' => 'Richiedere assenze'],
            ['group' => 'Azienda', 'key' => 'absences.manage', 'label' => 'Gestire assenze'],
            ['group' => 'Operatività', 'key' => 'updates.view', 'label' => 'Visualizzare aggiornamenti'],
            ['group' => 'Operatività', 'key' => 'updates.manage', 'label' => 'Gestire aggiornamenti'],
            ['group' => 'Amministrazione', 'key' => 'billing.view', 'label' => 'Visualizzare fatturazione'],
            ['group' => 'Amministrazione', 'key' => 'billing.manage', 'label' => 'Gestire fatturazione'],
            ['group' => 'Amministrazione', 'key' => 'users.view', 'label' => 'Visualizzare utenti'],
            ['group' => 'Amministrazione', 'key' => 'users.manage', 'label' => 'Gestire utenti'],
            ['group' => 'Profili dipendenti', 'key' => 'users.profile.personal.update', 'label' => 'Modificare dati personali degli utenti'],
            ['group' => 'Profili dipendenti', 'key' => 'users.profile.operational.update', 'label' => 'Modificare organizzazione, orari e smart working'],
            ['group' => 'Profili dipendenti', 'key' => 'users.profile.contract.view', 'label' => 'Visualizzare dati contrattuali riservati'],
            ['group' => 'Profili dipendenti', 'key' => 'users.profile.contract.update', 'label' => 'Modificare dati contrattuali riservati'],
            ['group' => 'Profili dipendenti', 'key' => 'users.profile.security.update', 'label' => 'Modificare ruolo, password e stato account'],
            ['group' => 'Amministrazione', 'key' => 'modules.view', 'label' => 'Visualizzare moduli'],
            ['group' => 'Amministrazione', 'key' => 'modules.manage', 'label' => 'Gestire moduli'],
            ['group' => 'Amministrazione', 'key' => 'ai_agency.view', 'label' => 'Visualizzare Agenzia AI'],
            ['group' => 'Amministrazione', 'key' => 'ai_agency.manage', 'label' => 'Gestire Agenzia AI'],
            ['group' => 'Sistema', 'key' => 'settings.manage', 'label' => 'Gestire impostazioni'],
        ];
    }

    public function matrix(): array
    {
        $values = Schema::hasTable('role_permissions')
            ? DB::table('role_permissions')->get()->groupBy('role')->map(fn ($rows) => $rows->pluck('allowed', 'permission'))->all()
            : [];

        return ['roles' => self::ROLES, 'permissions' => self::definitions(), 'values' => $values];
    }

    public function allows(string $role, string $permission): bool
    {
        if ($role === 'superadmin') {
            return true;
        }

        if (! Schema::hasTable('role_permissions')) {
            return false;
        }

        return (bool) DB::table('role_permissions')
            ->where('role', $role)
            ->where('permission', $permission)
            ->value('allowed');
    }

    public function permissionsForRole(string $role): array
    {
        if ($role === 'superadmin') {
            return array_column(self::definitions(), 'key');
        }

        if (! Schema::hasTable('role_permissions')) {
            return [];
        }

        return DB::table('role_permissions')
            ->where('role', $role)
            ->where('allowed', true)
            ->pluck('permission')
            ->values()
            ->all();
    }
}
