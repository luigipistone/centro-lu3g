<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('password_items')) {
            return;
        }

        Schema::table('password_items', function (Blueprint $table) {
            if (! Schema::hasColumn('password_items', 'compromised_count')) {
                $table->unsignedInteger('compromised_count')->default(0)->after('encrypted_password');
            }

            if (! Schema::hasColumn('password_items', 'compromised_checked_at')) {
                $table->timestamp('compromised_checked_at')->nullable()->after('compromised_count');
            }
        });
    }

    public function down(): void
    {
        if (! Schema::hasTable('password_items')) {
            return;
        }

        Schema::table('password_items', function (Blueprint $table) {
            if (Schema::hasColumn('password_items', 'compromised_checked_at')) {
                $table->dropColumn('compromised_checked_at');
            }

            if (Schema::hasColumn('password_items', 'compromised_count')) {
                $table->dropColumn('compromised_count');
            }
        });
    }
};
