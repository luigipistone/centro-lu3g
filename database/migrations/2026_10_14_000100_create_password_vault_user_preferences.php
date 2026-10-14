<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('password_vault_user_preferences', function (Blueprint $table) {
            $table->foreignUuid('password_vault_id')->constrained('password_vaults')->cascadeOnDelete();
            $table->foreignUuid('user_id')->constrained('users')->cascadeOnDelete();
            $table->boolean('show_in_overview')->default(true);
            $table->timestamps();
            $table->primary(['password_vault_id', 'user_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('password_vault_user_preferences');
    }
};
