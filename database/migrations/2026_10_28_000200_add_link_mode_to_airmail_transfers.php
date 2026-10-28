<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::table('airmail_transfers', function (Blueprint $table) {
            $table->string('mode', 10)->default('send');
            $table->string('recipient_email')->nullable()->change();
        });
    }

    public function down(): void
    {
        DB::table('airmail_transfers')->whereNull('recipient_email')->update(['recipient_email' => '']);
        Schema::table('airmail_transfers', function (Blueprint $table) {
            $table->string('recipient_email')->nullable(false)->change();
            $table->dropColumn('mode');
        });
    }
};
