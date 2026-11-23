<?php

declare(strict_types=1);

namespace Modules\IdentityAccess\Tests\Feature\Auth;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Auth;
use Tests\TestCase;

/**
 * Guards the "login must never be blocked by a leftover session" contract.
 *
 * The reported production bug was: a stale-but-valid session (surviving a
 * failed logout or a server restart) made the next login fail, because the
 * `guest` middleware redirected already-authenticated requests instead of
 * letting them re-authenticate. Login is now re-entrant.
 */
final class ReLoginResilienceTest extends TestCase
{
    use RefreshDatabase;

    private function postStatefulLogin(array $payload)
    {
        // First-party Referer makes Sanctum attach the stateful session
        // middleware during feature tests (mirrors the real SPA request).
        return $this
            ->withHeader('Referer', 'http://127.0.0.1:3001')
            ->postJson('/api/login', $payload);
    }

    public function test_login_succeeds_even_when_a_session_is_already_authenticated(): void
    {
        $user = User::factory()->create();

        // Simulate a leftover authenticated session from before a restart.
        $this->actingAs($user, 'web');
        $this->assertTrue(Auth::guard('web')->check());

        // Re-login must return the normal JSON contract, not a redirect.
        $this->postStatefulLogin([
            'email' => $user->email,
            'password' => 'password',
        ])
            ->assertOk()
            ->assertJsonPath('message', 'Login successful')
            ->assertJsonPath('user.email', $user->email);
    }

    public function test_failed_reauthentication_does_not_destroy_the_existing_session(): void
    {
        $user = User::factory()->create();
        $this->actingAs($user, 'web');

        $this->postStatefulLogin([
            'email' => $user->email,
            'password' => 'incorrect-password',
        ])->assertUnprocessable();

        $this->assertAuthenticatedAs($user, 'web');
        $this->getJson('/api/me')->assertOk();
    }

    public function test_logout_invalidates_the_session_so_me_is_no_longer_authorized(): void
    {
        $user = User::factory()->create();

        $this->postStatefulLogin([
            'email' => $user->email,
            'password' => 'password',
        ])->assertOk();

        // While the session is live, /api/me is authorized and a user is bound.
        $this->getJson('/api/me')->assertOk();
        $this->assertAuthenticated('web');

        // Logout tears the session down (204 No Content) and leaves no
        // authenticated user on the guard. `assertGuest()` is the idiomatic
        // Laravel/Breeze check that logout actually deauthenticated the session.
        $this->postJson('/api/logout')->assertNoContent();
        $this->assertGuest('web');
    }
}
