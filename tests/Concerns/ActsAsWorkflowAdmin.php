<?php

namespace Tests\Concerns;

use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

trait ActsAsWorkflowAdmin
{
    private function actingAsWorkflowAdmin(User $user): static
    {
        DB::table('user_roles')->updateOrInsert(
            ['user_id' => $user->id],
            ['id' => (string) Str::uuid(), 'role' => 'superadmin'],
        );

        return $this->actingAs($user);
    }
}
