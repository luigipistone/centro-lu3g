<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('attendance_holidays', function (Blueprint $table) {
            $table->boolean('recurs_annually')->default(false);
        });
    }

    public function down(): void
    {
        Schema::table('attendance_holidays', function (Blueprint $table) {
            $table->dropColumn('recurs_annually');
        });
    }
};
