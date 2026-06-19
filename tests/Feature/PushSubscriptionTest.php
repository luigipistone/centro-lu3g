<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class PushSubscriptionTest extends TestCase
{
    use RefreshDatabase;

    public function test_user_can_store_push_subscription(): void
    {
        $user = User::factory()->create();

        $this
            ->actingAs($user)
            ->postJson('/push-subscriptions', [
                'endpoint' => 'https://push.example.test/subscription/123',
                'keys' => [
                    'p256dh' => 'public-key',
                    'auth' => 'auth-token',
                ],
                'contentEncoding' => 'aes128gcm',
            ])
            ->assertOk()
            ->assertJson(['status' => 'ok']);

        $this->assertDatabaseHas('push_subscriptions', [
            'user_id' => $user->id,
            'endpoint' => 'https://push.example.test/subscription/123',
            'public_key' => 'public-key',
            'auth_token' => 'auth-token',
        ]);

        $this
            ->actingAs($user)
            ->postJson('/push-subscriptions', [
                'endpoint' => 'https://push.example.test/subscription/123',
                'keys' => [
                    'p256dh' => 'updated-public-key',
                    'auth' => 'updated-auth-token',
                ],
            ])
            ->assertOk();

        $this->assertSame(1, DB::table('push_subscriptions')->where('user_id', $user->id)->count());
        $this->assertDatabaseHas('push_subscriptions', [
            'user_id' => $user->id,
            'endpoint' => 'https://push.example.test/subscription/123',
            'public_key' => 'updated-public-key',
            'auth_token' => 'updated-auth-token',
        ]);
    }
}
