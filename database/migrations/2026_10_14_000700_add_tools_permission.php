<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

return new class extends Migration {
    public function up(): void
    {
        foreach (['superadmin', 'admin', 'editor', 'guest'] as $role) {
            DB::table('role_permissions')->updateOrInsert(
                ['role' => $role, 'permission' => 'tools.view'],
                ['id' => (string) Str::uuid(), 'allowed' => $role !== 'guest', 'created_at' => now(), 'updated_at' => now()],
            );
        }
    }

    public function down(): void
    {
        DB::table('role_permissions')->where('permission', 'tools.view')->delete();
    }
};
