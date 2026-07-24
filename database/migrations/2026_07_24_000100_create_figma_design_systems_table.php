<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('figma_design_systems', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->uuid('project_id')->unique();
            $table->string('figma_file_key');
            $table->json('colors');
            $table->json('typography');
            $table->string('status')->default('analyzed');
            $table->longText('error_message')->nullable();
            $table->uuid('analyzed_by')->nullable();
            $table->uuid('applied_by')->nullable();
            $table->timestamp('analyzed_at')->nullable();
            $table->timestamp('applied_at')->nullable();
            $table->timestamps();

            $table->foreign('project_id')->references('id')->on('projects')->cascadeOnDelete();
            $table->foreign('analyzed_by')->references('id')->on('users')->nullOnDelete();
            $table->foreign('applied_by')->references('id')->on('users')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('figma_design_systems');
    }
};
