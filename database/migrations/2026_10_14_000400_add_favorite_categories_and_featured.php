<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('favorite_categories', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->string('name', 120)->unique();
            $table->uuid('created_by')->nullable();
            $table->timestamps();
            $table->foreign('created_by')->references('id')->on('users')->nullOnDelete();
        });
        Schema::create('favorite_item_category', function (Blueprint $table) {
            $table->uuid('item_id');
            $table->uuid('category_id');
            $table->primary(['item_id', 'category_id']);
            $table->foreign('item_id')->references('id')->on('favorite_items')->cascadeOnDelete();
            $table->foreign('category_id')->references('id')->on('favorite_categories')->cascadeOnDelete();
        });
        Schema::table('favorite_items', function (Blueprint $table) {
            $table->boolean('is_featured')->default(false)->index();
        });
    }

    public function down(): void
    {
        Schema::table('favorite_items', fn (Blueprint $table) => $table->dropColumn('is_featured'));
        Schema::dropIfExists('favorite_item_category');
        Schema::dropIfExists('favorite_categories');
    }
};
