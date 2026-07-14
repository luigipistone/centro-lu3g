<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('ai_agency_runs', function (Blueprint $table) {
            $table->longText('pending_output')->nullable()->after('proposal');
            $table->json('pending_usage')->nullable()->after('pending_output');
        });
    }

    public function down(): void
    {
        Schema::table('ai_agency_runs', function (Blueprint $table) {
            $table->dropColumn(['pending_output', 'pending_usage']);
        });
    }
};
