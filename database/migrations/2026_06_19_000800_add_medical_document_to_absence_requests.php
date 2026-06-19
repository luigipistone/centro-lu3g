<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('absence_requests', function (Blueprint $table) {
            $table->string('medical_document_path')->nullable()->after('inps_code');
            $table->string('medical_document_name')->nullable()->after('medical_document_path');
            $table->string('medical_document_mime')->nullable()->after('medical_document_name');
        });
    }

    public function down(): void
    {
        Schema::table('absence_requests', function (Blueprint $table) {
            $table->dropColumn(['medical_document_path', 'medical_document_name', 'medical_document_mime']);
        });
    }
};
