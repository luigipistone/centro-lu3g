<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('api_write_keys', function (Blueprint $t) {
            $t->id();
            $t->foreignUuid('connection_id')->constrained('api_connections')->cascadeOnDelete();
            $t->string('request_key', 128);
            $t->string('request_hash', 64);
            $t->string('resource', 20);
            $t->uuid('resource_id');
            $t->uuid('parent_id')->nullable();
            $t->timestamps();
            $t->unique(['connection_id', 'request_key']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('api_write_keys');
    }
};
