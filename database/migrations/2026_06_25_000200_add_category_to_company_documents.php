<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasColumn('company_documents', 'category')) {
            Schema::table('company_documents', function (Blueprint $table) {
                $table->string('category')->default('documenti_vari')->after('description')->index();
            });
        }

        if (! Schema::hasColumn('company_documents', 'document_year')) {
            Schema::table('company_documents', function (Blueprint $table) {
                $table->unsignedSmallInteger('document_year')->nullable()->after('category')->index();
            });
        }
    }

    public function down(): void
    {
        if (Schema::hasColumn('company_documents', 'document_year')) {
            Schema::table('company_documents', function (Blueprint $table) {
                $table->dropIndex(['document_year']);
                $table->dropColumn('document_year');
            });
        }

        if (Schema::hasColumn('company_documents', 'category')) {
            Schema::table('company_documents', function (Blueprint $table) {
                $table->dropIndex(['category']);
                $table->dropColumn('category');
            });
        }
    }
};
