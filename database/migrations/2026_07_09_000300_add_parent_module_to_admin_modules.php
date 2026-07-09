<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('admin_modules') || Schema::hasColumn('admin_modules', 'parent_module_id')) {
            return;
        }

        Schema::table('admin_modules', function (Blueprint $table) {
            $table->uuid('parent_module_id')->nullable()->after('admin_module_folder_id');
            $table->foreign('parent_module_id')->references('id')->on('admin_modules')->nullOnDelete();
        });
    }

    public function down(): void
    {
        if (! Schema::hasTable('admin_modules') || ! Schema::hasColumn('admin_modules', 'parent_module_id')) {
            return;
        }

        Schema::table('admin_modules', function (Blueprint $table) {
            $table->dropForeign(['parent_module_id']);
            $table->dropColumn('parent_module_id');
        });
    }
};
