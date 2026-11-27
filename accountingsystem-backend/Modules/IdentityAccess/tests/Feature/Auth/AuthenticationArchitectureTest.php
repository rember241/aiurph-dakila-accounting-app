<?php

declare(strict_types=1);

namespace Modules\IdentityAccess\Tests\Feature\Auth;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Route;
use Laravel\Sanctum\Sanctum;
use Modules\IdentityAccess\Http\Controllers\Auth\CurrentUserController;
use Modules\IdentityAccess\Http\Controllers\Auth\LoginController;
use Modules\IdentityAccess\Http\Controllers\Auth\LogoutController;
use Tests\TestCase;

final class AuthenticationArchitectureTest extends TestCase
{
    use RefreshDatabase;

    public function test_auth_routes_are_owned_by_identity_access_without_changing_paths(): void
    {
        $this->assertSame(LoginController::class, Route::getRoutes()->getByName('login')?->getControllerClass());
        $this->assertSame(LogoutController::class, Route::getRoutes()->getByName('logout')?->getControllerClass());
        $this->assertSame(CurrentUserController::class, Route::getRoutes()->getByName('me')?->getControllerClass());
    }

    public function test_login_preserves_the_existing_frontend_response_contract(): void
    {
        $user = User::factory()->create();

        $this->withHeader('Referer', 'http://127.0.0.1:3001')
            ->postJson('/api/login', [
                'email' => $user->email,
                'password' => 'password',
            ])
            ->assertOk()
            ->assertExactJson([
                'message' => 'Login successful',
                'user' => [
                    'id' => (string) $user->getKey(),
                    'name' => $user->name,
                    'email' => $user->email,
                ],
            ]);
    }

    public function test_current_user_preserves_the_unwrapped_frontend_contract(): void
    {
        $user = User::factory()->create();
        Sanctum::actingAs($user, [], 'sanctum');

        $this->getJson('/api/me')
            ->assertOk()
            ->assertJsonPath('id', (string) $user->getKey())
            ->assertJsonPath('email', $user->email)
            ->assertJsonPath('company', null)
            ->assertJsonPath('onboarding.required', true)
            ->assertJsonMissingPath('data');
    }

    public function test_authenticated_browser_can_extend_its_session(): void
    {
        config(['session.lifetime' => 10]);
        $this->freezeTime();

        $user = User::factory()->create();
        $browser = $this->withHeader('Referer', 'http://127.0.0.1:3001');

        $browser->postJson('/api/login', ['email' => $user->email, 'password' => 'password'])
            ->assertOk()
            ->assertHeader('X-Session-Expires-At', (string) now()->addMinutes(10)->getTimestamp());

        $this->travel(3)->minutes();

        $browser->postJson('/api/session/extend')
            ->assertNoContent()
            ->assertHeader('X-Session-Expires-At', (string) now()->addMinutes(10)->getTimestamp())
            ->assertHeader('X-Session-Warning-Seconds', '120');

        $this->travelBack();
    }

    public function test_anonymous_browser_cannot_extend_a_session(): void
    {
        $this->withHeader('Referer', 'http://127.0.0.1:3001')
            ->postJson('/api/session/extend')
            ->assertUnauthorized()
            ->assertHeaderMissing('X-Session-Expires-At');
    }
}
