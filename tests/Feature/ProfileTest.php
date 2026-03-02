<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ProfileTest extends TestCase
{
    use RefreshDatabase;

    public function test_profile_page_route_is_disabled(): void
    {
        $user = User::factory()->create();

        $response = $this
            ->actingAs($user)
            ->get('/profile');

        $response->assertNotFound();
    }

    public function test_profile_information_can_not_be_updated_when_route_is_disabled(): void
    {
        $user = User::factory()->create();
        $oldName = $user->name;
        $oldEmail = $user->email;

        $response = $this
            ->actingAs($user)
            ->patch('/profile', [
                'name' => 'Test User',
                'email' => 'test@example.com',
            ]);

        $response->assertNotFound();

        $user->refresh();

        $this->assertSame($oldName, $user->name);
        $this->assertSame($oldEmail, $user->email);
    }

    public function test_profile_update_route_returns_not_found_for_authenticated_user(): void
    {
        $user = User::factory()->create();

        $response = $this
            ->actingAs($user)
            ->patch('/profile', [
                'name' => 'Test User',
                'email' => $user->email,
            ]);

        $response->assertNotFound();
        $this->assertNotNull($user->refresh());
    }

    public function test_user_can_not_delete_their_account_when_profile_route_is_disabled(): void
    {
        $user = User::factory()->create();

        $response = $this
            ->actingAs($user)
            ->delete('/profile', [
                'password' => 'password',
            ]);

        $response->assertNotFound();

        $this->assertAuthenticatedAs($user);
        $this->assertNotNull($user->fresh());
    }

    public function test_delete_profile_route_returns_not_found_with_wrong_password_too(): void
    {
        $user = User::factory()->create();

        $response = $this
            ->actingAs($user)
            ->from('/profile')
            ->delete('/profile', [
                'password' => 'wrong-password',
            ]);

        $response->assertNotFound();

        $this->assertNotNull($user->fresh());
    }
}
