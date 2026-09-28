<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('company_document_manager_access')) {
            Schema::create('company_document_manager_access', function (Blueprint $table) {
                $table->uuid('id')->primary();
                $table->foreignUuid('company_document_id')->constrained('company_documents')->cascadeOnDelete();
                $table->foreignUuid('user_id')->constrained('users')->cascadeOnDelete();
                $table->foreignUuid('granted_by')->nullable()->constrained('users')->nullOnDelete();
                $table->timestamps();
                $table->unique(['company_document_id', 'user_id']);
            });
        }

        if (! Schema::hasTable('company_message_schedules')) {
            Schema::create('company_message_schedules', function (Blueprint $table) {
                $table->uuid('id')->primary();
                $table->string('title');
                $table->longText('body')->nullable();
                $table->string('audience');
                $table->json('user_ids')->nullable();
                $table->json('group_ids')->nullable();
                $table->string('recurrence')->default('none');
                $table->date('ends_on')->nullable();
                $table->dateTime('next_run_at')->index();
                $table->boolean('active')->default(true)->index();
                $table->foreignUuid('created_by')->nullable()->constrained('users')->nullOnDelete();
                $table->timestamps();
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('company_message_schedules');
        Schema::dropIfExists('company_document_manager_access');
    }
};
