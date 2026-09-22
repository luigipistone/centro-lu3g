<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('account_archive_requests', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->uuid('user_id')->nullable()->index();
            $table->uuid('requested_by')->nullable()->index();
            $table->uuid('reviewed_by')->nullable()->index();
            $table->string('user_name');
            $table->string('user_email');
            $table->string('user_role', 40)->nullable();
            $table->string('status', 20)->default('pending')->index();
            $table->text('reason');
            $table->text('review_note')->nullable();
            $table->json('linked_summary')->nullable();
            $table->timestamp('reviewed_at')->nullable();
            $table->timestamps();
            $table->foreign('user_id')->references('id')->on('users')->nullOnDelete();
            $table->foreign('requested_by')->references('id')->on('users')->nullOnDelete();
            $table->foreign('reviewed_by')->references('id')->on('users')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('account_archive_requests');
    }
};
