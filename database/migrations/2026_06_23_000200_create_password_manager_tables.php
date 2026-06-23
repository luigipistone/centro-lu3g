<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('password_vaults')) {
            Schema::create('password_vaults', function (Blueprint $table) {
                $table->uuid('id')->primary();
                $table->string('name');
                $table->longText('description')->nullable();
                $table->string('color', 24)->nullable();
                $table->uuid('created_by')->nullable();
                $table->timestamps();
                $table->foreign('created_by')->references('id')->on('users')->nullOnDelete();
            });
        }

        if (! Schema::hasTable('password_groups')) {
            Schema::create('password_groups', function (Blueprint $table) {
                $table->uuid('id')->primary();
                $table->string('name');
                $table->longText('description')->nullable();
                $table->uuid('created_by')->nullable();
                $table->timestamps();
                $table->foreign('created_by')->references('id')->on('users')->nullOnDelete();
            });
        }

        if (! Schema::hasTable('password_group_user')) {
            Schema::create('password_group_user', function (Blueprint $table) {
                $table->uuid('id')->primary();
                $table->uuid('password_group_id');
                $table->uuid('user_id');
                $table->timestamps();
                $table->unique(['password_group_id', 'user_id']);
                $table->foreign('password_group_id')->references('id')->on('password_groups')->cascadeOnDelete();
                $table->foreign('user_id')->references('id')->on('users')->cascadeOnDelete();
            });
        }

        if (! Schema::hasTable('password_items')) {
            Schema::create('password_items', function (Blueprint $table) {
                $table->uuid('id')->primary();
                $table->uuid('password_vault_id')->nullable();
                $table->string('title');
                $table->string('url')->nullable();
                $table->string('username')->nullable();
                $table->longText('encrypted_password')->nullable();
                $table->longText('notes')->nullable();
                $table->json('tags')->nullable();
                $table->json('custom_fields')->nullable();
                $table->timestamp('expires_at')->nullable();
                $table->boolean('favorite')->default(false);
                $table->uuid('client_id')->nullable();
                $table->uuid('project_id')->nullable();
                $table->uuid('created_by')->nullable();
                $table->uuid('updated_by')->nullable();
                $table->timestamps();
                $table->foreign('password_vault_id')->references('id')->on('password_vaults')->nullOnDelete();
                $table->foreign('client_id')->references('id')->on('clients')->nullOnDelete();
                $table->foreign('project_id')->references('id')->on('projects')->nullOnDelete();
                $table->foreign('created_by')->references('id')->on('users')->nullOnDelete();
                $table->foreign('updated_by')->references('id')->on('users')->nullOnDelete();
            });
        }

        if (! Schema::hasTable('password_item_user')) {
            Schema::create('password_item_user', function (Blueprint $table) {
                $table->uuid('id')->primary();
                $table->uuid('password_item_id');
                $table->uuid('user_id');
                $table->string('permission')->default('view');
                $table->timestamps();
                $table->unique(['password_item_id', 'user_id']);
                $table->foreign('password_item_id')->references('id')->on('password_items')->cascadeOnDelete();
                $table->foreign('user_id')->references('id')->on('users')->cascadeOnDelete();
            });
        }

        if (! Schema::hasTable('password_item_group')) {
            Schema::create('password_item_group', function (Blueprint $table) {
                $table->uuid('id')->primary();
                $table->uuid('password_item_id');
                $table->uuid('password_group_id');
                $table->string('permission')->default('view');
                $table->timestamps();
                $table->unique(['password_item_id', 'password_group_id']);
                $table->foreign('password_item_id')->references('id')->on('password_items')->cascadeOnDelete();
                $table->foreign('password_group_id')->references('id')->on('password_groups')->cascadeOnDelete();
            });
        }

        if (! Schema::hasTable('password_audit_logs')) {
            Schema::create('password_audit_logs', function (Blueprint $table) {
                $table->uuid('id')->primary();
                $table->uuid('password_item_id')->nullable();
                $table->uuid('user_id')->nullable();
                $table->string('action');
                $table->longText('details')->nullable();
                $table->timestamps();
                $table->foreign('password_item_id')->references('id')->on('password_items')->cascadeOnDelete();
                $table->foreign('user_id')->references('id')->on('users')->nullOnDelete();
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('password_audit_logs');
        Schema::dropIfExists('password_item_group');
        Schema::dropIfExists('password_item_user');
        Schema::dropIfExists('password_items');
        Schema::dropIfExists('password_group_user');
        Schema::dropIfExists('password_groups');
        Schema::dropIfExists('password_vaults');
    }
};
