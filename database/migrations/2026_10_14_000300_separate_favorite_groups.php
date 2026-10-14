<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('favorite_groups', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->string('name');
            $table->text('description')->nullable();
            $table->uuid('created_by')->nullable();
            $table->timestamps();
            $table->foreign('created_by')->references('id')->on('users')->nullOnDelete();
        });
        Schema::create('favorite_group_user', function (Blueprint $table) {
            $table->uuid('group_id');
            $table->uuid('user_id');
            $table->primary(['group_id', 'user_id']);
            $table->foreign('group_id')->references('id')->on('favorite_groups')->cascadeOnDelete();
            $table->foreign('user_id')->references('id')->on('users')->cascadeOnDelete();
        });
        Schema::create('favorite_folder_team', function (Blueprint $table) {
            $table->uuid('folder_id');
            $table->uuid('group_id');
            $table->primary(['folder_id', 'group_id']);
            $table->foreign('folder_id')->references('id')->on('favorite_folders')->cascadeOnDelete();
            $table->foreign('group_id')->references('id')->on('favorite_groups')->cascadeOnDelete();
        });

        // Preserve current sharing while making future group edits independent from Password.
        $legacyIds = DB::table('favorite_folder_group')->distinct()->pluck('group_id');
        foreach (DB::table('password_groups')->whereIn('id', $legacyIds)->get() as $group) {
            DB::table('favorite_groups')->insert([
                'id' => $group->id, 'name' => $group->name, 'description' => $group->description,
                'created_by' => $group->created_by, 'created_at' => $group->created_at,
                'updated_at' => $group->updated_at,
            ]);
            foreach (DB::table('password_group_user')->where('password_group_id', $group->id)->pluck('user_id') as $userId) {
                DB::table('favorite_group_user')->insert(['group_id' => $group->id, 'user_id' => $userId]);
            }
        }
        foreach (DB::table('favorite_folder_group')->get() as $share) {
            DB::table('favorite_folder_team')->insert(['folder_id' => $share->folder_id, 'group_id' => $share->group_id]);
        }
        Schema::drop('favorite_folder_group');
    }

    public function down(): void
    {
        Schema::create('favorite_folder_group', function (Blueprint $table) {
            $table->uuid('folder_id');
            $table->uuid('group_id');
            $table->primary(['folder_id', 'group_id']);
            $table->foreign('folder_id')->references('id')->on('favorite_folders')->cascadeOnDelete();
            $table->foreign('group_id')->references('id')->on('password_groups')->cascadeOnDelete();
        });
        foreach (DB::table('favorite_folder_team')->get() as $share) {
            if (DB::table('password_groups')->where('id', $share->group_id)->exists()) {
                DB::table('favorite_folder_group')->insert(['folder_id' => $share->folder_id, 'group_id' => $share->group_id]);
            }
        }
        Schema::dropIfExists('favorite_folder_team');
        Schema::dropIfExists('favorite_group_user');
        Schema::dropIfExists('favorite_groups');
    }
};
