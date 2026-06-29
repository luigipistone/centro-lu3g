<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('company_messages')) {
            Schema::create('company_messages', function (Blueprint $table) {
                $table->uuid('id')->primary();
                $table->string('title');
                $table->longText('body')->nullable();
                $table->string('audience')->default('all');
                $table->uuid('created_by')->nullable();
                $table->timestamps();
                $table->foreign('created_by')->references('id')->on('users')->nullOnDelete();
            });
        }

        if (! Schema::hasTable('company_message_user')) {
            Schema::create('company_message_user', function (Blueprint $table) {
                $table->uuid('id')->primary();
                $table->uuid('company_message_id');
                $table->uuid('user_id');
                $table->timestamps();
                $table->unique(['company_message_id', 'user_id']);
                $table->foreign('company_message_id')->references('id')->on('company_messages')->cascadeOnDelete();
                $table->foreign('user_id')->references('id')->on('users')->cascadeOnDelete();
            });
        }

        if (! Schema::hasTable('company_message_group')) {
            Schema::create('company_message_group', function (Blueprint $table) {
                $table->uuid('id')->primary();
                $table->uuid('company_message_id');
                $table->uuid('document_group_id');
                $table->timestamps();
                $table->unique(['company_message_id', 'document_group_id']);
                $table->foreign('company_message_id')->references('id')->on('company_messages')->cascadeOnDelete();
                $table->foreign('document_group_id')->references('id')->on('document_groups')->cascadeOnDelete();
            });
        }

        if (! Schema::hasTable('company_message_reads')) {
            Schema::create('company_message_reads', function (Blueprint $table) {
                $table->uuid('id')->primary();
                $table->uuid('company_message_id');
                $table->uuid('user_id');
                $table->timestamp('opened_at')->nullable();
                $table->timestamp('read_at')->nullable();
                $table->timestamps();
                $table->unique(['company_message_id', 'user_id']);
                $table->foreign('company_message_id')->references('id')->on('company_messages')->cascadeOnDelete();
                $table->foreign('user_id')->references('id')->on('users')->cascadeOnDelete();
            });
        }

        if (! Schema::hasColumn('notifications', 'company_message_id')) {
            Schema::table('notifications', function (Blueprint $table) {
                $table->uuid('company_message_id')->nullable()->after('company_document_id');
                $table->foreign('company_message_id')->references('id')->on('company_messages')->nullOnDelete();
            });
        }
    }

    public function down(): void
    {
        if (Schema::hasColumn('notifications', 'company_message_id')) {
            Schema::table('notifications', function (Blueprint $table) {
                $table->dropForeign(['company_message_id']);
                $table->dropColumn('company_message_id');
            });
        }

        Schema::dropIfExists('company_message_reads');
        Schema::dropIfExists('company_message_group');
        Schema::dropIfExists('company_message_user');
        Schema::dropIfExists('company_messages');
    }
};
