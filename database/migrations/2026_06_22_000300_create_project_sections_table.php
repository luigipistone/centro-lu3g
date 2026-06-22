<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('project_sections')) {
            Schema::create('project_sections', function (Blueprint $table) {
                $table->uuid('id')->primary();
                $table->uuid('project_id');
                $table->string('name');
                $table->unsignedInteger('position')->default(0);
                $table->timestamps();

                $table->foreign('project_id')->references('id')->on('projects')->cascadeOnDelete();
            });
        }

        if (! Schema::hasColumn('tasks', 'project_section_id')) {
            Schema::table('tasks', function (Blueprint $table) {
                $table->uuid('project_section_id')->nullable()->after('project_id');
            });
        }
    }

    public function down(): void
    {
        if (Schema::hasColumn('tasks', 'project_section_id')) {
            Schema::table('tasks', function (Blueprint $table) {
                $table->dropColumn('project_section_id');
            });
        }

        Schema::dropIfExists('project_sections');
    }
};
