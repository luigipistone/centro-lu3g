<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('password_vault_user')) {
            Schema::create('password_vault_user', function (Blueprint $table) {
                $table->uuid('id')->primary();
                $table->uuid('password_vault_id');
                $table->uuid('user_id');
                $table->timestamps();
                $table->unique(['password_vault_id', 'user_id']);
                $table->foreign('password_vault_id')->references('id')->on('password_vaults')->cascadeOnDelete();
                $table->foreign('user_id')->references('id')->on('users')->cascadeOnDelete();
            });
        }

        if (! Schema::hasTable('password_vault_group')) {
            Schema::create('password_vault_group', function (Blueprint $table) {
                $table->uuid('id')->primary();
                $table->uuid('password_vault_id');
                $table->uuid('password_group_id');
                $table->timestamps();
                $table->unique(['password_vault_id', 'password_group_id']);
                $table->foreign('password_vault_id')->references('id')->on('password_vaults')->cascadeOnDelete();
                $table->foreign('password_group_id')->references('id')->on('password_groups')->cascadeOnDelete();
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('password_vault_group');
        Schema::dropIfExists('password_vault_user');
    }
};
