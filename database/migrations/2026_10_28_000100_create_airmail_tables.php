<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('airmail_settings', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->text('encrypted_password');
            $table->timestamps();
        });

        Schema::create('airmail_transfers', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('user_id')->constrained('users')->cascadeOnDelete();
            $table->string('recipient_email');
            $table->text('message')->nullable();
            $table->string('status', 20)->default('uploading');
            $table->string('token_hash', 64)->nullable()->unique();
            $table->text('encrypted_token')->nullable();
            $table->timestamp('expires_at');
            $table->timestamp('email_sent_at')->nullable();
            $table->timestamps();
            $table->index(['user_id', 'created_at']);
            $table->index(['status', 'expires_at']);
        });

        Schema::create('airmail_files', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('transfer_id')->constrained('airmail_transfers')->cascadeOnDelete();
            $table->string('original_name');
            $table->string('stored_name');
            $table->unsignedBigInteger('size');
            $table->unsignedInteger('uploaded_chunks')->default(0);
            $table->string('status', 20)->default('uploading');
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('airmail_files');
        Schema::dropIfExists('airmail_transfers');
        Schema::dropIfExists('airmail_settings');
    }
};
