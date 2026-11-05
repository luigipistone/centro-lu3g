<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::table('client_services', function (Blueprint $table) {
            $table->boolean('manual_association')->default(true);
            $table->boolean('report_association')->default(false);
        });
        Schema::table('report_client_links', function (Blueprint $table) {
            $table->string('mapping_revision')->nullable();
        });
    }

    public function down(): void
    {
        Schema::table('report_client_links', fn (Blueprint $table) => $table->dropColumn('mapping_revision'));
        Schema::table('client_services', fn (Blueprint $table) => $table->dropColumn(['manual_association', 'report_association']));
    }
};
