<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

return new class extends Migration
{
    private const DEFAULTS = [
        'documents.user_overview.view' => false,
        'documents.messages.view' => true,
        'documents.groups.view' => true,
        'absences.presence.view' => true,
        'absences.reports.view' => true,
    ];

    public function up(): void
    {
        if (! Schema::hasTable('role_permissions')) {
            return;
        }

        foreach (['superadmin', 'admin', 'editor', 'guest'] as $role) {
            foreach (self::DEFAULTS as $permission => $managerDefault) {
                if (DB::table('role_permissions')->where('role', $role)->where('permission', $permission)->exists()) {
                    continue;
                }
                DB::table('role_permissions')->insert([
                    'id' => (string) Str::uuid(),
                    'role' => $role,
                    'permission' => $permission,
                    'allowed' => $role === 'superadmin' || ($role === 'admin' && $managerDefault),
                    'created_at' => now(),
                    'updated_at' => now(),
                ]);
            }
        }
    }

    public function down(): void
    {
        if (Schema::hasTable('role_permissions')) {
            DB::table('role_permissions')->whereIn('permission', array_keys(self::DEFAULTS))->delete();
        }
    }
};
