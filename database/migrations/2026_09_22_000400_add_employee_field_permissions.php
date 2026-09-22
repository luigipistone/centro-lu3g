<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

return new class extends Migration
{
    public function up(): void
    {
        $permissions = [
            'users.profile.personal.update' => ['superadmin', 'admin'],
            'users.profile.operational.update' => ['superadmin', 'admin'],
            'users.profile.contract.view' => ['superadmin', 'admin'],
            'users.profile.contract.update' => ['superadmin'],
            'users.profile.security.update' => ['superadmin'],
        ];

        foreach (['superadmin', 'admin', 'editor', 'guest'] as $role) {
            foreach ($permissions as $permission => $allowedRoles) {
                $key = ['role' => $role, 'permission' => $permission];
                $values = ['allowed' => in_array($role, $allowedRoles, true), 'updated_at' => now()];

                if (DB::table('role_permissions')->where($key)->exists()) {
                    DB::table('role_permissions')->where($key)->update($values);
                } else {
                    DB::table('role_permissions')->insert([...$key, ...$values, 'id' => (string) Str::uuid(), 'created_at' => now()]);
                }
            }
        }
    }

    public function down(): void
    {
        DB::table('role_permissions')->whereIn('permission', [
            'users.profile.personal.update', 'users.profile.operational.update', 'users.profile.contract.view',
            'users.profile.contract.update', 'users.profile.security.update',
        ])->delete();
    }
};
