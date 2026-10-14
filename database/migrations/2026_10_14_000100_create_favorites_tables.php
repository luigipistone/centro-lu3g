<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('favorite_folders', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->string('name');
            $table->string('visibility', 20)->default('personal');
            $table->uuid('created_by')->nullable();
            $table->timestamps();
            $table->foreign('created_by')->references('id')->on('users')->nullOnDelete();
            $table->index(['created_by', 'visibility']);
        });
        Schema::create('favorite_folder_user', function (Blueprint $table) {
            $table->uuid('folder_id');
            $table->uuid('user_id');
            $table->primary(['folder_id', 'user_id']);
            $table->foreign('folder_id')->references('id')->on('favorite_folders')->cascadeOnDelete();
            $table->foreign('user_id')->references('id')->on('users')->cascadeOnDelete();
        });
        Schema::create('favorite_folder_group', function (Blueprint $table) {
            $table->uuid('folder_id');
            $table->uuid('group_id');
            $table->primary(['folder_id', 'group_id']);
            $table->foreign('folder_id')->references('id')->on('favorite_folders')->cascadeOnDelete();
            $table->foreign('group_id')->references('id')->on('password_groups')->cascadeOnDelete();
        });
        Schema::create('favorite_items', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->uuid('folder_id');
            $table->uuid('created_by')->nullable();
            $table->string('title');
            $table->string('url', 2048);
            $table->text('description')->nullable();
            $table->timestamps();
            $table->foreign('folder_id')->references('id')->on('favorite_folders')->cascadeOnDelete();
            $table->foreign('created_by')->references('id')->on('users')->nullOnDelete();
            $table->index(['folder_id', 'created_at']);
        });

        foreach (['superadmin', 'admin', 'editor', 'guest'] as $role) {
            foreach (['favorites.view', 'favorites.manage'] as $permission) {
                DB::table('role_permissions')->insert([
                    'id' => (string) Str::uuid(), 'role' => $role, 'permission' => $permission,
                    'allowed' => $permission === 'favorites.view' ? $role !== 'guest' : in_array($role, ['superadmin', 'admin'], true),
                    'created_at' => now(), 'updated_at' => now(),
                ]);
            }
        }
    }

    public function down(): void
    {
        DB::table('role_permissions')->whereIn('permission', ['favorites.view', 'favorites.manage'])->delete();
        Schema::dropIfExists('favorite_items');
        Schema::dropIfExists('favorite_folder_group');
        Schema::dropIfExists('favorite_folder_user');
        Schema::dropIfExists('favorite_folders');
    }
};
