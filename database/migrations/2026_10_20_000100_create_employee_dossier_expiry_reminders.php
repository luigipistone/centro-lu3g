<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('employee_dossier_expiry_reminders', function (Blueprint $table) {
            $table->id();
            $table->uuid('dossier_item_id');
            $table->uuid('user_id');
            $table->string('stage', 20);
            $table->timestamps();
            $table->unique(['dossier_item_id', 'user_id', 'stage'], 'dossier_expiry_reminder_unique');
            $table->foreign('dossier_item_id')->references('id')->on('employee_dossier_items')->cascadeOnDelete();
            $table->foreign('user_id')->references('id')->on('users')->cascadeOnDelete();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('employee_dossier_expiry_reminders');
    }
};
