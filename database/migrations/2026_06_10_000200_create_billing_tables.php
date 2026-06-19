<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('document_settings', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->string('company_name')->default('Il Centro');
            $table->string('legal_form')->nullable();
            $table->string('vat_number')->nullable();
            $table->string('tax_code')->nullable();
            $table->string('tax_regime')->nullable();
            $table->string('street')->nullable();
            $table->string('street_number')->nullable();
            $table->string('postal_code')->nullable();
            $table->string('city')->nullable();
            $table->string('province')->nullable();
            $table->string('country')->nullable();
            $table->string('email')->nullable();
            $table->string('pec')->nullable();
            $table->string('phone')->nullable();
            $table->string('sdi_code')->nullable();
            $table->string('iban')->nullable();
            $table->string('bic_swift')->nullable();
            $table->string('bank_name')->nullable();
            $table->decimal('share_capital', 12, 2)->nullable();
            $table->string('rea_number')->nullable();
            $table->string('default_payment_method')->nullable();
            $table->unsignedSmallInteger('default_payment_terms_days')->nullable();
            $table->decimal('default_withholding_pct', 5, 2)->nullable();
            $table->string('default_pension_fund_label')->nullable();
            $table->decimal('default_pension_fund_pct', 5, 2)->nullable();
            $table->decimal('bollo_threshold', 12, 2)->nullable();
            $table->decimal('bollo_amount', 12, 2)->nullable();
            $table->boolean('bollo_charged_to_client')->nullable();
            $table->longText('footer_notes')->nullable();
            $table->timestamps();
        });

        Schema::create('document_numbering', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->string('doc_type');
            $table->unsignedSmallInteger('year');
            $table->string('prefix')->default('');
            $table->string('format')->default('{prefix}{year}/{seq}');
            $table->unsignedInteger('current_seq')->default(0);
            $table->boolean('yearly_reset')->default(true);
            $table->timestamps();
            $table->unique(['doc_type', 'year']);
        });

        Schema::create('subscriptions', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->uuid('client_id');
            $table->string('name');
            $table->longText('description')->nullable();
            $table->decimal('amount', 12, 2)->default(0);
            $table->decimal('vat_rate', 5, 2)->default(22);
            $table->string('vat_nature_code')->nullable();
            $table->unsignedInteger('frequency_value')->default(1);
            $table->string('frequency_unit')->default('month');
            $table->date('start_date');
            $table->date('end_date')->nullable();
            $table->date('next_invoice_date');
            $table->unsignedSmallInteger('payment_terms_days')->nullable();
            $table->boolean('auto_generate')->default(false);
            $table->boolean('active')->default(true);
            $table->longText('notes')->nullable();
            $table->uuid('created_by')->nullable();
            $table->timestamps();
            $table->foreign('client_id')->references('id')->on('clients')->cascadeOnDelete();
            $table->foreign('created_by')->references('id')->on('users')->nullOnDelete();
        });

        Schema::create('documents', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->uuid('client_id');
            $table->uuid('subscription_id')->nullable();
            $table->uuid('parent_document_id')->nullable();
            $table->string('doc_type');
            $table->string('status')->default('draft');
            $table->string('number')->nullable();
            $table->unsignedInteger('seq')->nullable();
            $table->unsignedSmallInteger('year')->nullable();
            $table->date('issue_date');
            $table->date('due_date')->nullable();
            $table->string('currency')->default('EUR');
            $table->string('payment_method')->nullable();
            $table->unsignedSmallInteger('payment_terms_days')->nullable();
            $table->longText('causale')->nullable();
            $table->longText('notes')->nullable();
            $table->longText('footer_notes')->nullable();
            $table->decimal('withholding_pct', 5, 2)->nullable();
            $table->string('pension_fund_label')->nullable();
            $table->decimal('pension_fund_pct', 5, 2)->nullable();
            $table->boolean('apply_bollo')->default(false);
            $table->decimal('bollo_amount', 12, 2)->nullable();
            $table->decimal('total_taxable', 12, 2)->default(0);
            $table->decimal('total_discount', 12, 2)->default(0);
            $table->decimal('total_vat', 12, 2)->default(0);
            $table->decimal('total_pension_fund', 12, 2)->default(0);
            $table->decimal('total_withholding', 12, 2)->default(0);
            $table->decimal('total_amount', 12, 2)->default(0);
            $table->decimal('total_paid', 12, 2)->default(0);
            $table->string('sdi_status')->nullable();
            $table->timestamp('xml_generated_at')->nullable();
            $table->uuid('created_by')->nullable();
            $table->timestamps();
            $table->foreign('client_id')->references('id')->on('clients')->cascadeOnDelete();
            $table->foreign('subscription_id')->references('id')->on('subscriptions')->nullOnDelete();
            $table->foreign('parent_document_id')->references('id')->on('documents')->nullOnDelete();
            $table->foreign('created_by')->references('id')->on('users')->nullOnDelete();
        });

        Schema::create('document_lines', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->uuid('document_id');
            $table->unsignedInteger('position')->default(0);
            $table->longText('description');
            $table->decimal('quantity', 12, 2)->default(1);
            $table->string('unit')->nullable();
            $table->decimal('unit_price', 12, 2)->default(0);
            $table->decimal('discount_pct', 5, 2)->default(0);
            $table->decimal('vat_rate', 5, 2)->default(22);
            $table->string('vat_nature_code')->nullable();
            $table->decimal('subtotal', 12, 2)->default(0);
            $table->timestamps();
            $table->foreign('document_id')->references('id')->on('documents')->cascadeOnDelete();
        });

        Schema::create('document_payments', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->uuid('document_id');
            $table->decimal('amount', 12, 2)->default(0);
            $table->date('paid_at');
            $table->string('method')->nullable();
            $table->longText('notes')->nullable();
            $table->uuid('created_by')->nullable();
            $table->timestamps();
            $table->foreign('document_id')->references('id')->on('documents')->cascadeOnDelete();
            $table->foreign('created_by')->references('id')->on('users')->nullOnDelete();
        });

        foreach (['document_activity', 'document_emails'] as $name) {
            Schema::create($name, function (Blueprint $table) use ($name) {
                $table->uuid('id')->primary();
                $table->uuid('document_id');
                if ($name === 'document_activity') {
                    $table->uuid('user_id')->nullable();
                    $table->string('action');
                    $table->string('field')->nullable();
                    $table->longText('old_value')->nullable();
                    $table->longText('new_value')->nullable();
                } else {
                    $table->uuid('sent_by')->nullable();
                    $table->string('channel');
                    $table->string('recipient');
                    $table->string('cc')->nullable();
                    $table->string('subject')->nullable();
                    $table->string('status')->default('sent');
                    $table->longText('error')->nullable();
                    $table->timestamp('sent_at')->useCurrent();
                }
                $table->timestamps();
                $table->foreign('document_id')->references('id')->on('documents')->cascadeOnDelete();
            });
        }

        Schema::create('email_settings', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->boolean('smtp_enabled')->default(false);
            $table->string('smtp_host')->nullable();
            $table->unsignedSmallInteger('smtp_port')->nullable();
            $table->string('smtp_username')->nullable();
            $table->text('smtp_password')->nullable();
            $table->boolean('smtp_secure')->default(true);
            $table->string('smtp_from_email')->nullable();
            $table->string('smtp_from_name')->nullable();
            $table->string('smtp_reply_to')->nullable();
            $table->string('pec_username')->nullable();
            $table->text('pec_password')->nullable();
            $table->timestamps();
        });

        Schema::create('backup_runs', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->string('frequency');
            $table->string('status')->default('running');
            $table->timestamp('started_at')->useCurrent();
            $table->timestamp('finished_at')->nullable();
            $table->unsignedBigInteger('size_bytes')->nullable();
            $table->unsignedInteger('tables_count')->nullable();
            $table->string('storage_path')->nullable();
            $table->longText('error')->nullable();
        });

        Schema::create('client_service_updates', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->uuid('client_id');
            $table->uuid('service_id');
            $table->uuid('responsible_user_id')->nullable();
            $table->string('cadence')->nullable();
            $table->string('contact')->nullable();
            $table->string('report_url')->nullable();
            $table->longText('notes')->nullable();
            $table->timestamps();
            $table->foreign('client_id')->references('id')->on('clients')->cascadeOnDelete();
            $table->foreign('service_id')->references('id')->on('services')->cascadeOnDelete();
            $table->foreign('responsible_user_id')->references('id')->on('users')->nullOnDelete();
        });
    }

    public function down(): void
    {
        foreach ([
            'client_service_updates', 'backup_runs', 'email_settings', 'document_emails',
            'document_activity', 'document_payments', 'document_lines', 'documents',
            'subscriptions', 'document_numbering', 'document_settings',
        ] as $table) {
            Schema::dropIfExists($table);
        }
    }
};
