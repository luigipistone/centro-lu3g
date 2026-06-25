<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('password_items') || ! Schema::hasColumn('password_items', 'url')) {
            return;
        }

        Schema::table('password_items', function (Blueprint $table) {
            $table->text('url')->nullable()->change();
        });
    }

    public function down(): void
    {
        if (! Schema::hasTable('password_items') || ! Schema::hasColumn('password_items', 'url')) {
            return;
        }

        Schema::table('password_items', function (Blueprint $table) {
            $table->string('url')->nullable()->change();
        });
    }
};
