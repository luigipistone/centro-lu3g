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
        Schema::create('employee_dossier_items', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->uuid('user_id')->index();
            $table->string('type', 60)->index();
            $table->string('title');
            $table->string('identifier')->nullable();
            $table->date('issued_at')->nullable();
            $table->date('expires_at')->nullable()->index();
            $table->string('level')->nullable();
            $table->string('classification', 30)->default('standard')->index();
            $table->string('fitness_status', 40)->nullable();
            $table->longText('notes')->nullable();
            $table->string('file_path')->nullable();
            $table->string('file_name')->nullable();
            $table->string('file_mime')->nullable();
            $table->unsignedInteger('version')->default(1);
            $table->uuid('replaces_id')->nullable()->index();
            $table->timestamp('replaced_at')->nullable();
            $table->timestamp('accepted_at')->nullable();
            $table->uuid('created_by')->nullable();
            $table->timestamps();

            $table->foreign('user_id')->references('id')->on('users')->cascadeOnDelete();
            $table->foreign('replaces_id')->references('id')->on('employee_dossier_items')->nullOnDelete();
            $table->foreign('created_by')->references('id')->on('users')->nullOnDelete();
        });

        $permissions = [
            'users.dossier.standard.view' => ['superadmin', 'admin', 'editor'],
            'users.dossier.standard.manage' => ['superadmin', 'admin'],
            'users.dossier.contract.view' => ['superadmin', 'admin'],
            'users.dossier.contract.manage' => ['superadmin'],
            'users.dossier.medical.status' => ['superadmin', 'admin'],
            'users.dossier.medical.manage' => ['superadmin'],
            'users.dossier.admin.view' => ['superadmin'],
            'users.dossier.admin.manage' => ['superadmin'],
        ];

        foreach (['superadmin', 'admin', 'editor', 'guest'] as $role) {
            foreach ($permissions as $permission => $allowedRoles) {
                DB::table('role_permissions')->insert([
                    'id' => (string) Str::uuid(),
                    'role' => $role,
                    'permission' => $permission,
                    'allowed' => in_array($role, $allowedRoles, true),
                    'created_at' => now(),
                    'updated_at' => now(),
                ]);
            }
        }
    }

    public function down(): void
    {
        DB::table('role_permissions')->whereIn('permission', [
            'users.dossier.standard.view', 'users.dossier.standard.manage',
            'users.dossier.contract.view', 'users.dossier.contract.manage',
            'users.dossier.medical.status', 'users.dossier.medical.manage',
            'users.dossier.admin.view', 'users.dossier.admin.manage',
        ])->delete();
        Schema::dropIfExists('employee_dossier_items');
    }
};
