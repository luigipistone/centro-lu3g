<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasColumn('password_vaults', 'visibility')) {
            Schema::table('password_vaults', function (Blueprint $table) {
                $table->string('visibility')->default('personal')->after('color');
            });
        }

        DB::table('password_vaults')
            ->whereExists(function ($query) {
                $query->select(DB::raw(1))
                    ->from('password_vault_user')
                    ->whereColumn('password_vault_user.password_vault_id', 'password_vaults.id');
            })
            ->orWhereExists(function ($query) {
                $query->select(DB::raw(1))
                    ->from('password_vault_group')
                    ->whereColumn('password_vault_group.password_vault_id', 'password_vaults.id');
            })
            ->update(['visibility' => 'shared']);
    }

    public function down(): void
    {
        if (Schema::hasColumn('password_vaults', 'visibility')) {
            Schema::table('password_vaults', function (Blueprint $table) {
                $table->dropColumn('visibility');
            });
        }
    }
};
