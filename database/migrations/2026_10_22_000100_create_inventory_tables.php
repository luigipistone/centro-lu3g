<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('inventory_items', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->string('type', 20);
            $table->string('name');
            $table->string('category')->nullable();
            $table->string('brand')->nullable();
            $table->string('model')->nullable();
            $table->unsignedSmallInteger('model_year')->nullable();
            $table->string('license_type')->nullable();
            $table->unsignedInteger('capacity_total')->nullable();
            $table->unsignedInteger('used_count')->nullable();
            $table->date('renewal_date')->nullable();
            $table->string('access_url', 2048)->nullable();
            $table->text('notes')->nullable();
            $table->uuid('created_by')->nullable();
            $table->timestamps();
            $table->foreign('created_by')->references('id')->on('users')->nullOnDelete();
            $table->index(['type', 'category']);
        });

        Schema::create('inventory_units', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->uuid('item_id');
            $table->string('serial_number')->nullable();
            $table->uuid('assigned_user_id')->nullable();
            $table->string('office')->nullable();
            $table->string('condition', 20)->default('available');
            $table->date('purchased_at')->nullable();
            $table->date('warranty_until')->nullable();
            $table->text('notes')->nullable();
            $table->timestamps();
            $table->foreign('item_id')->references('id')->on('inventory_items')->cascadeOnDelete();
            $table->foreign('assigned_user_id')->references('id')->on('users')->nullOnDelete();
            $table->index(['item_id', 'assigned_user_id']);
        });

        foreach (['superadmin', 'admin', 'editor', 'guest'] as $role) {
            foreach (['inventory.view', 'inventory.manage'] as $permission) {
                DB::table('role_permissions')->updateOrInsert(
                    ['role' => $role, 'permission' => $permission],
                    ['id' => (string) Str::uuid(), 'allowed' => in_array($role, ['superadmin', 'admin'], true), 'created_at' => now(), 'updated_at' => now()],
                );
            }
        }

        $physical = [
            ['iMac 23" M3 2023', 'Apple', 'iMac M3', 2023, 'Computer', 9],
            ['iMac 27" Intel 2019', 'Apple', 'iMac Intel', 2019, 'Computer', 1],
            ['iMac 21" Intel 2019', 'Apple', 'iMac Intel', 2019, 'Computer', 1],
            ['Monitor HP 21"', 'HP', 'Monitor 21"', null, 'Monitor', 1],
            ['iPad Pro 2021', 'Apple', 'iPad Pro', 2021, 'Tablet', 1],
            ['iPad Air 2021', 'Apple', 'iPad Air', 2021, 'Tablet', 1],
            ['iPhone XS', 'Apple', 'iPhone XS', null, 'Telefono', 8],
        ];
        foreach ($physical as [$name, $brand, $model, $year, $category, $quantity]) {
            $id = (string) Str::uuid();
            DB::table('inventory_items')->insert([
                'id' => $id, 'type' => 'physical', 'name' => $name, 'brand' => $brand,
                'model' => $model, 'model_year' => $year, 'category' => $category,
                'created_at' => now(), 'updated_at' => now(),
            ]);
            for ($i = 0; $i < $quantity; $i++) {
                DB::table('inventory_units')->insert([
                    'id' => (string) Str::uuid(), 'item_id' => $id,
                    'condition' => 'available', 'created_at' => now(), 'updated_at' => now(),
                ]);
            }
        }

        $digital = [
            ['WPML', 'Lifetime', null, 'https://wpml.org/it/account/'],
            ['WP All Import', 'Lifetime', null, 'https://www.wpallimport.com/portal/'],
            ['WP All Export', 'Lifetime', null, 'https://www.wpallimport.com/portal/'],
            ['Crocoblock', 'Lifetime', null, 'https://account.crocoblock.com/'],
            ['Elementor', 'Abbonamento', 1000, 'https://my.elementor.com/websites/'],
            ['RankMath', 'Abbonamento', 100, 'https://rankmath.com/my-account/subscriptions/'],
            ['Dynamic For Elementor', 'Lifetime', null, 'https://shop.dynamic.ooo/my-account/'],
            ['CleanTalk', 'Abbonamento', null, 'https://cleantalk.org/my/session'],
            ['Duplicator Pro', 'Lifetime', null, 'https://duplicator.com/login/'],
            ['Pubbly', 'Lifetime', null, 'https://accounts.pabbly.com/login'],
            ["Nicolai's Template Club", 'Lifetime', null, 'https://nicolaipalmkvist.com/'],
            ['WP Rocket', 'Abbonamento', 50, 'https://wp-rocket.me/account/'],
            ['Essential Addons', 'Da verificare', null, 'https://essential-addons.com/'],
            ['Animation Addon Pro', 'Singolo sito', 1, 'https://animation-addons.com/'],
            ['WP Mail SMTP', 'Abbonamento', 20, 'https://wpmailsmtp.com/'],
            ['ACF Pro', 'Abbonamento', null, 'https://www.advancedcustomfields.com/my-account/'],
            ['Revolution Slider', 'Abbonamento', 250, 'https://account.sliderrevolution.com/portal/dashboard/'],
            ['Clickly', 'Lifetime', null, 'https://clickly.techyeti.com/home'],
        ];
        foreach ($digital as [$name, $licenseType, $capacity, $url]) {
            DB::table('inventory_items')->insert([
                'id' => (string) Str::uuid(), 'type' => 'digital', 'name' => $name,
                'category' => 'Licenza e plugin', 'license_type' => $licenseType,
                'capacity_total' => $capacity, 'access_url' => $url,
                'notes' => $name === 'WP Rocket' ? 'Presente due volte nel documento originale: verificare se si tratta della stessa licenza.' : null,
                'created_at' => now(), 'updated_at' => now(),
            ]);
        }
    }

    public function down(): void
    {
        DB::table('role_permissions')->whereIn('permission', ['inventory.view', 'inventory.manage'])->delete();
        Schema::dropIfExists('inventory_units');
        Schema::dropIfExists('inventory_items');
    }
};
