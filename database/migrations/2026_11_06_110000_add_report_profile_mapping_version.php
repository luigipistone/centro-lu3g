<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::table('report_client_links', fn (Blueprint $table) => $table->unsignedInteger('profile_mapping_version')->default(1));
    }
    public function down(): void
    {
        Schema::table('report_client_links', fn (Blueprint $table) => $table->dropColumn('profile_mapping_version'));
    }
};
