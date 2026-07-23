<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('figma_settings', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->string('team_id')->nullable();
            $table->longText('encrypted_token')->nullable();
            $table->timestamps();
        });

        Schema::table('projects', function (Blueprint $table) {
            $table->string('figma_project_id')->nullable()->after('figma_url');
            $table->string('figma_file_key')->nullable()->after('figma_project_id');
            $table->string('figma_file_name')->nullable()->after('figma_file_key');
            $table->text('figma_thumbnail_url')->nullable()->after('figma_file_name');
        });
    }

    public function down(): void
    {
        Schema::table('projects', function (Blueprint $table) {
            $table->dropColumn([
                'figma_project_id',
                'figma_file_key',
                'figma_file_name',
                'figma_thumbnail_url',
            ]);
        });

        Schema::dropIfExists('figma_settings');
    }
};
