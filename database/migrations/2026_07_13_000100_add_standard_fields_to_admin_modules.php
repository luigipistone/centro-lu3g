<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('admin_modules')) {
            return;
        }

        if (! Schema::hasColumn('admin_modules', 'available_components')) {
            Schema::table('admin_modules', function (Blueprint $table) {
                $table->longText('available_components')->nullable()->after('description');
            });
        }

        if (! Schema::hasColumn('admin_modules', 'decision_criteria')) {
            Schema::table('admin_modules', function (Blueprint $table) {
                $table->longText('decision_criteria')->nullable()->after('available_components');
            });
        }
    }

    public function down(): void
    {
        if (! Schema::hasTable('admin_modules')) {
            return;
        }

        if (Schema::hasColumn('admin_modules', 'decision_criteria')) {
            Schema::table('admin_modules', function (Blueprint $table) {
                $table->dropColumn('decision_criteria');
            });
        }

        if (Schema::hasColumn('admin_modules', 'available_components')) {
            Schema::table('admin_modules', function (Blueprint $table) {
                $table->dropColumn('available_components');
            });
        }
    }
};
