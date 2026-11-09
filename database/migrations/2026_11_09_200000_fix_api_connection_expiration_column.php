<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // A DATETIME cannot acquire MariaDB's implicit first-TIMESTAMP ON UPDATE behavior.
        Schema::table('api_connections', fn (Blueprint $table) => $table->dateTime('expires_at')->change());
    }

    public function down(): void
    {
        Schema::table('api_connections', fn (Blueprint $table) => $table->timestamp('expires_at')->useCurrentOnUpdate(false)->default('2000-01-01 00:00:00')->change());
    }
};
