<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('document_groups')) {
            Schema::create('document_groups', function (Blueprint $table) {
                $table->uuid('id')->primary();
                $table->string('name');
                $table->longText('description')->nullable();
                $table->uuid('created_by')->nullable();
                $table->timestamps();
                $table->foreign('created_by')->references('id')->on('users')->nullOnDelete();
            });
        }

        if (! Schema::hasTable('document_group_user')) {
            Schema::create('document_group_user', function (Blueprint $table) {
                $table->uuid('id')->primary();
                $table->uuid('document_group_id');
                $table->uuid('user_id');
                $table->timestamps();
                $table->unique(['document_group_id', 'user_id']);
                $table->foreign('document_group_id')->references('id')->on('document_groups')->cascadeOnDelete();
                $table->foreign('user_id')->references('id')->on('users')->cascadeOnDelete();
            });
        }

        if (! Schema::hasTable('company_documents')) {
            Schema::create('company_documents', function (Blueprint $table) {
                $table->uuid('id')->primary();
                $table->string('title');
                $table->longText('description')->nullable();
                $table->string('audience')->default('all');
                $table->string('file_path');
                $table->string('file_name');
                $table->string('file_mime')->default('application/pdf');
                $table->unsignedBigInteger('file_size')->default(0);
                $table->uuid('created_by')->nullable();
                $table->timestamps();
                $table->foreign('created_by')->references('id')->on('users')->nullOnDelete();
            });
        }

        if (! Schema::hasTable('company_document_user')) {
            Schema::create('company_document_user', function (Blueprint $table) {
                $table->uuid('id')->primary();
                $table->uuid('company_document_id');
                $table->uuid('user_id');
                $table->timestamps();
                $table->unique(['company_document_id', 'user_id']);
                $table->foreign('company_document_id')->references('id')->on('company_documents')->cascadeOnDelete();
                $table->foreign('user_id')->references('id')->on('users')->cascadeOnDelete();
            });
        }

        if (! Schema::hasTable('company_document_group')) {
            Schema::create('company_document_group', function (Blueprint $table) {
                $table->uuid('id')->primary();
                $table->uuid('company_document_id');
                $table->uuid('document_group_id');
                $table->timestamps();
                $table->unique(['company_document_id', 'document_group_id']);
                $table->foreign('company_document_id')->references('id')->on('company_documents')->cascadeOnDelete();
                $table->foreign('document_group_id')->references('id')->on('document_groups')->cascadeOnDelete();
            });
        }

        if (! Schema::hasTable('company_document_reads')) {
            Schema::create('company_document_reads', function (Blueprint $table) {
                $table->uuid('id')->primary();
                $table->uuid('company_document_id');
                $table->uuid('user_id');
                $table->timestamp('opened_at')->nullable();
                $table->timestamp('read_at')->nullable();
                $table->timestamps();
                $table->unique(['company_document_id', 'user_id']);
                $table->foreign('company_document_id')->references('id')->on('company_documents')->cascadeOnDelete();
                $table->foreign('user_id')->references('id')->on('users')->cascadeOnDelete();
            });
        }

        if (! Schema::hasColumn('notifications', 'company_document_id')) {
            Schema::table('notifications', function (Blueprint $table) {
                $table->uuid('company_document_id')->nullable()->after('task_id');
                $table->foreign('company_document_id')->references('id')->on('company_documents')->nullOnDelete();
            });
        }
    }

    public function down(): void
    {
        Schema::table('notifications', function (Blueprint $table) {
            $table->dropForeign(['company_document_id']);
            $table->dropColumn('company_document_id');
        });

        Schema::dropIfExists('company_document_reads');
        Schema::dropIfExists('company_document_group');
        Schema::dropIfExists('company_document_user');
        Schema::dropIfExists('company_documents');
        Schema::dropIfExists('document_group_user');
        Schema::dropIfExists('document_groups');
    }
};
