<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    private const FIELDS = ['legal_form', 'source', 'pec', 'sdi_code', 'iban', 'bic_swift',
        'vat_treatment', 'payment_terms_days', 'is_pa', 'electronic_invoicing_address'];

    public function up(): void
    {
        Schema::table('clients', fn (Blueprint $table) => $table->dropColumn(self::FIELDS));
    }

    public function down(): void
    {
        Schema::table('clients', function (Blueprint $table) {
            foreach (array_diff(self::FIELDS, ['payment_terms_days', 'is_pa']) as $field) $table->string($field)->nullable();
            $table->unsignedSmallInteger('payment_terms_days')->nullable();
            $table->boolean('is_pa')->default(false);
        });
    }
};
