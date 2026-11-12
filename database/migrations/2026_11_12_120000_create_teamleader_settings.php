<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('teamleader_settings', function (Blueprint $table) {
            $table->unsignedInteger('id')->primary();
            $table->string('client_id');
            $table->text('client_secret');
            $table->text('access_token')->nullable();
            $table->text('refresh_token')->nullable();
            $table->timestamp('expires_at')->nullable();
            $table->uuid('connection_id')->nullable();
            $table->timestamps();
        });
    }
    public function down(): void { Schema::dropIfExists('teamleader_settings'); }
};
