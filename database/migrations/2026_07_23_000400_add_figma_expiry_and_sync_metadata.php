<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('figma_settings', function (Blueprint $table) {
            $table->date('token_expires_at')->nullable()->after('encrypted_token');
        });

        Schema::table('projects', function (Blueprint $table) {
            $table->timestamp('figma_last_modified_at')->nullable()->after('figma_thumbnail_url');
        });

        DB::table('figma_settings')
            ->whereNotNull('encrypted_token')
            ->whereNull('token_expires_at')
            ->update(['token_expires_at' => now('Europe/Rome')->addDays(90)->toDateString()]);
    }

    public function down(): void
    {
        Schema::table('figma_settings', function (Blueprint $table) {
            $table->dropColumn('token_expires_at');
        });

        Schema::table('projects', function (Blueprint $table) {
            $table->dropColumn('figma_last_modified_at');
        });
    }
};
