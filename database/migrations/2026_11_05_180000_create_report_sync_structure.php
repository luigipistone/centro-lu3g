<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('clients', function (Blueprint $table) {
            $table->string('electronic_invoicing_address')->nullable();
        });

        Schema::create('report_client_links', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->uuid('client_id')->unique();
            $table->string('external_id')->unique();
            $table->string('teamleader_id')->nullable()->index();
            $table->string('source_type')->nullable();
            $table->string('report_revision')->nullable();
            $table->string('report_profile_revision')->nullable();
            $table->string('report_services_revision')->nullable();
            $table->timestamp('native_updated_at')->nullable();
            $table->timestamp('last_synced_at')->nullable();
            $table->timestamps();
            $table->foreign('client_id')->references('id')->on('clients')->cascadeOnDelete();
        });

        // Report-owned history stays separate from manually assigned client_services.
        Schema::create('report_client_services', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->uuid('report_client_link_id');
            $table->string('external_id');
            $table->string('name');
            $table->string('status');
            $table->boolean('scheduled')->default(false);
            $table->boolean('unverified')->default(false);
            $table->json('areas')->nullable();
            $table->timestamp('last_seen_at')->nullable();
            $table->timestamps();
            $table->unique(['report_client_link_id', 'external_id'], 'report_client_service_identity');
            $table->foreign('report_client_link_id')->references('id')->on('report_client_links')->cascadeOnDelete();
        });

        Schema::create('report_area_mappings', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->string('external_id')->unique();
            $table->string('name');
            $table->uuid('service_id')->nullable();
            $table->boolean('excluded')->default(false);
            $table->timestamps();
            $table->foreign('service_id')->references('id')->on('services')->nullOnDelete();
        });

        Schema::create('report_sync_runs', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->boolean('dry_run')->default(true);
            $table->string('status')->default('pending');
            $table->string('snapshot_version')->nullable();
            $table->unsignedInteger('created_count')->default(0);
            $table->unsignedInteger('updated_count')->default(0);
            $table->unsignedInteger('unchanged_count')->default(0);
            $table->unsignedInteger('error_count')->default(0);
            $table->timestamp('started_at')->nullable();
            $table->timestamp('finished_at')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('report_sync_runs');
        Schema::dropIfExists('report_area_mappings');
        Schema::dropIfExists('report_client_services');
        Schema::dropIfExists('report_client_links');
        Schema::table('clients', function (Blueprint $table) {
            $table->dropColumn('electronic_invoicing_address');
        });
    }
};
