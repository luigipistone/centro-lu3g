<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('company_document_versions', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->uuid('company_document_id');
            $table->unsignedInteger('version');
            $table->string('title');
            $table->longText('description')->nullable();
            $table->string('category');
            $table->unsignedSmallInteger('document_year');
            $table->string('file_path');
            $table->string('file_name');
            $table->string('file_mime');
            $table->unsignedBigInteger('file_size');
            $table->uuid('changed_by')->nullable();
            $table->timestamp('created_at');
            $table->unique(['company_document_id', 'version']);
            $table->foreign('company_document_id')->references('id')->on('company_documents')->cascadeOnDelete();
            $table->foreign('changed_by')->references('id')->on('users')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('company_document_versions');
    }
};
