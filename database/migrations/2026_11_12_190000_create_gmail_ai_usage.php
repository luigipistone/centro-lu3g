<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('gmail_ai_usage', function (Blueprint $t) {
            $t->uuid('id')->primary();
            $t->decimal('estimated_cost_eur', 12, 6);
            $t->timestamp('created_at')->index();
        });
    }
    public function down(): void { Schema::dropIfExists('gmail_ai_usage'); }
};
