<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('api_connections', fn (Blueprint $table) => $table->string('site', 500)->nullable()->change());
    }

    public function down(): void
    {
        DB::table('api_connections')->whereNull('site')->update(['site' => '']);
        Schema::table('api_connections', fn (Blueprint $table) => $table->string('site', 500)->nullable(false)->change());
    }
};
