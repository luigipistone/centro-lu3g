<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('role_permissions')) {
            DB::table('role_permissions')->whereIn('permission', ['billing.view', 'billing.manage'])->delete();
        }
        if (Schema::hasTable('section_availability')) {
            DB::table('section_availability')->where('section_key', 'billing')->delete();
        }

        foreach ([
            'document_emails',
            'document_activity',
            'document_payments',
            'document_lines',
            'documents',
            'subscriptions',
            'document_numbering',
            'document_settings',
        ] as $table) {
            Schema::dropIfExists($table);
        }
    }

    public function down(): void
    {
        // Removed invoice and subscription data cannot be reconstructed by rollback.
    }
};
