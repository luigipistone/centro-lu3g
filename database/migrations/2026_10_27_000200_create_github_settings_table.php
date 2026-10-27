<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('github_settings', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->longText('encrypted_token');
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('github_settings');
    }
};
