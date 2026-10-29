<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::table('absence_requests', function (Blueprint $table) {
            $table->timestamp('revocation_requested_at')->nullable();
            $table->text('revocation_decision_reason')->nullable();
        });
    }

    public function down(): void
    {
        Schema::table('absence_requests', function (Blueprint $table) {
            $table->dropColumn(['revocation_requested_at', 'revocation_decision_reason']);
        });
    }
};
