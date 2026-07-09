<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('admin_module_folders')) {
            Schema::create('admin_module_folders', function (Blueprint $table) {
                $table->uuid('id')->primary();
                $table->string('name');
                $table->longText('description')->nullable();
                $table->string('color', 24)->default('#2563eb');
                $table->uuid('created_by')->nullable();
                $table->timestamps();

                $table->foreign('created_by')->references('id')->on('users')->nullOnDelete();
            });
        }

        if (! Schema::hasTable('admin_modules')) {
            Schema::create('admin_modules', function (Blueprint $table) {
                $table->uuid('id')->primary();
                $table->uuid('admin_module_folder_id');
                $table->string('name');
                $table->string('category')->nullable();
                $table->longText('description')->nullable();
                $table->json('required_inputs')->nullable();
                $table->longText('rules')->nullable();
                $table->longText('output')->nullable();
                $table->json('allowed_agents')->nullable();
                $table->boolean('active')->default(true);
                $table->uuid('created_by')->nullable();
                $table->timestamps();

                $table->foreign('admin_module_folder_id')->references('id')->on('admin_module_folders')->cascadeOnDelete();
                $table->foreign('created_by')->references('id')->on('users')->nullOnDelete();
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('admin_modules');
        Schema::dropIfExists('admin_module_folders');
    }
};
