<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('project_messages')) {
            Schema::create('project_messages', function (Blueprint $table) {
                $table->uuid('id')->primary();
                $table->uuid('project_id');
                $table->uuid('user_id')->nullable();
                $table->longText('content');
                $table->timestamps();
                $table->foreign('project_id')->references('id')->on('projects')->cascadeOnDelete();
                $table->foreign('user_id')->references('id')->on('users')->nullOnDelete();
            });
        }

        if (! Schema::hasTable('project_files')) {
            Schema::create('project_files', function (Blueprint $table) {
                $table->uuid('id')->primary();
                $table->uuid('project_id');
                $table->uuid('uploaded_by')->nullable();
                $table->string('kind')->default('file');
                $table->string('name');
                $table->string('original_name');
                $table->string('path');
                $table->string('mime_type')->nullable();
                $table->unsignedBigInteger('size')->default(0);
                $table->timestamps();
                $table->foreign('project_id')->references('id')->on('projects')->cascadeOnDelete();
                $table->foreign('uploaded_by')->references('id')->on('users')->nullOnDelete();
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('project_files');
        Schema::dropIfExists('project_messages');
    }
};
