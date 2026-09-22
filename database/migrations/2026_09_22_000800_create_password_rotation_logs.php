<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('password_rotation_logs', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->uuid('password_item_id');
            $table->uuid('user_id')->nullable();
            $table->timestamps();
            $table->index(['password_item_id', 'created_at']);
            $table->foreign('password_item_id')->references('id')->on('password_items')->cascadeOnDelete();
            $table->foreign('user_id')->references('id')->on('users')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('password_rotation_logs');
    }
};
