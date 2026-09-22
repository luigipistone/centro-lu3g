<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->string('account_status', 20)->default('active')->index();
            $table->timestamp('suspended_at')->nullable();
            $table->timestamp('archived_at')->nullable();
        });

        Schema::create('role_permissions', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->string('role', 40);
            $table->string('permission', 120);
            $table->boolean('allowed')->default(false);
            $table->timestamps();
            $table->unique(['role', 'permission']);
        });

        Schema::create('audit_logs', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->uuid('user_id')->nullable()->index();
            $table->string('user_name')->nullable();
            $table->string('user_role', 40)->nullable();
            $table->string('action', 80);
            $table->string('area', 80)->nullable()->index();
            $table->string('route_name')->nullable();
            $table->string('method', 12);
            $table->string('subject_id')->nullable()->index();
            $table->unsignedSmallInteger('status_code')->nullable();
            $table->string('ip_address', 45)->nullable();
            $table->text('user_agent')->nullable();
            $table->json('metadata')->nullable();
            $table->timestamps();
            $table->index('created_at');
        });

        $permissions = [
            'dashboard.view', 'clients.view', 'clients.create', 'clients.update', 'clients.delete',
            'projects.view', 'projects.create', 'projects.update', 'projects.delete',
            'tasks.view', 'tasks.create', 'tasks.update', 'tasks.delete', 'calendar.view',
            'documents.view', 'documents.manage', 'passwords.view', 'passwords.manage',
            'absences.request', 'absences.manage', 'updates.view', 'updates.manage',
            'billing.view', 'billing.manage', 'users.view', 'users.manage',
            'modules.view', 'modules.manage', 'ai_agency.view', 'ai_agency.manage', 'settings.manage',
        ];
        $defaults = [
            'superadmin' => $permissions,
            'admin' => array_values(array_diff($permissions, ['settings.manage', 'users.manage'])),
            'editor' => ['dashboard.view', 'clients.view', 'projects.view', 'projects.update', 'tasks.view', 'tasks.create', 'tasks.update', 'tasks.delete', 'calendar.view', 'documents.view', 'passwords.view', 'absences.request', 'updates.view', 'updates.manage'],
            'guest' => ['dashboard.view', 'projects.view', 'tasks.view', 'tasks.update', 'calendar.view'],
        ];
        $now = now();
        foreach ($defaults as $role => $allowed) {
            foreach ($permissions as $permission) {
                DB::table('role_permissions')->insert([
                    'id' => (string) Str::uuid(), 'role' => $role, 'permission' => $permission,
                    'allowed' => in_array($permission, $allowed, true), 'created_at' => $now, 'updated_at' => $now,
                ]);
            }
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('audit_logs');
        Schema::dropIfExists('role_permissions');
        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn(['account_status', 'suspended_at', 'archived_at']);
        });
    }
};
