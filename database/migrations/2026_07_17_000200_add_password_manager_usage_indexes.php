<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('password_items', function (Blueprint $table) {
            $table->index(['password_vault_id', 'updated_at'], 'password_items_vault_updated_idx');
        });

        Schema::table('password_vault_user', function (Blueprint $table) {
            $table->index(['user_id', 'password_vault_id'], 'password_vault_user_user_vault_idx');
        });

        Schema::table('password_group_user', function (Blueprint $table) {
            $table->index(['user_id', 'password_group_id'], 'password_group_user_user_group_idx');
        });

        Schema::table('password_item_user', function (Blueprint $table) {
            $table->index(['user_id', 'password_item_id'], 'password_item_user_user_item_idx');
        });

        Schema::table('password_item_group', function (Blueprint $table) {
            $table->index(['password_group_id', 'password_item_id'], 'password_item_group_group_item_idx');
        });
    }

    public function down(): void
    {
        Schema::table('password_item_group', function (Blueprint $table) {
            $table->dropIndex('password_item_group_group_item_idx');
        });

        Schema::table('password_item_user', function (Blueprint $table) {
            $table->dropIndex('password_item_user_user_item_idx');
        });

        Schema::table('password_group_user', function (Blueprint $table) {
            $table->dropIndex('password_group_user_user_group_idx');
        });

        Schema::table('password_vault_user', function (Blueprint $table) {
            $table->dropIndex('password_vault_user_user_vault_idx');
        });

        Schema::table('password_items', function (Blueprint $table) {
            $table->dropIndex('password_items_vault_updated_idx');
        });
    }
};
