<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('task_dependencies')) {
            Schema::create('task_dependencies', function (Blueprint $table) {
                $table->uuid('id')->primary();
                $table->uuid('task_id');
                $table->uuid('depends_on_task_id');
                $table->timestamps();
                $table->unique(['task_id', 'depends_on_task_id']);
                $table->foreign('task_id')->references('id')->on('tasks')->cascadeOnDelete();
                $table->foreign('depends_on_task_id')->references('id')->on('tasks')->cascadeOnDelete();
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('task_dependencies');
    }
};
