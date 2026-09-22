<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('password_category_fields', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->string('category', 60)->index();
            $table->string('label', 120);
            $table->string('field_key', 140);
            $table->uuid('created_by')->nullable();
            $table->timestamps();
            $table->unique(['category', 'field_key']);
            $table->foreign('created_by')->references('id')->on('users')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('password_category_fields');
    }
};
