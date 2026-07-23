<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('wordpress_provisionings', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->uuid('project_id')->unique();
            $table->uuid('client_id');
            $table->uuid('started_by')->nullable();
            $table->uuid('credential_item_id')->nullable();
            $table->string('folder_slug');
            $table->string('site_url');
            $table->string('status')->default('queued');
            $table->string('current_step')->nullable();
            $table->unsignedTinyInteger('progress')->default(0);
            $table->string('database_name')->nullable();
            $table->string('database_user')->nullable();
            $table->longText('encrypted_database_password')->nullable();
            $table->longText('log')->nullable();
            $table->longText('error_message')->nullable();
            $table->timestamp('started_at')->nullable();
            $table->timestamp('completed_at')->nullable();
            $table->timestamps();

            $table->foreign('project_id')->references('id')->on('projects')->cascadeOnDelete();
            $table->foreign('client_id')->references('id')->on('clients')->cascadeOnDelete();
            $table->foreign('started_by')->references('id')->on('users')->nullOnDelete();
            $table->foreign('credential_item_id')->references('id')->on('password_items')->nullOnDelete();
            $table->index(['status', 'updated_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('wordpress_provisionings');
    }
};
