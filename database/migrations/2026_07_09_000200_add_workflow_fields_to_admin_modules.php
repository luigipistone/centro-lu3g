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

        if (! Schema::hasColumn('admin_modules', 'dependency_module_ids')) {
            Schema::table('admin_modules', function (Blueprint $table) {
                $table->json('dependency_module_ids')->nullable()->after('required_inputs');
            });
        }

        if (! Schema::hasColumn('admin_modules', 'version')) {
            Schema::table('admin_modules', function (Blueprint $table) {
                $table->string('version', 40)->default('1.0')->after('category');
            });
        }

        if (! Schema::hasColumn('admin_modules', 'status')) {
            Schema::table('admin_modules', function (Blueprint $table) {
                $table->string('status', 40)->default('draft')->after('version');
            });
        }
    }

    public function down(): void
    {
        if (! Schema::hasTable('admin_modules')) {
            return;
        }

        if (Schema::hasColumn('admin_modules', 'dependency_module_ids')) {
            Schema::table('admin_modules', function (Blueprint $table) {
                $table->dropColumn('dependency_module_ids');
            });
        }

        if (Schema::hasColumn('admin_modules', 'status')) {
            Schema::table('admin_modules', function (Blueprint $table) {
                $table->dropColumn('status');
            });
        }

        if (Schema::hasColumn('admin_modules', 'version')) {
            Schema::table('admin_modules', function (Blueprint $table) {
                $table->dropColumn('version');
            });
        }
    }
};
