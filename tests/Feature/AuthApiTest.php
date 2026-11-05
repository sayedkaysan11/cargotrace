<?php

namespace Tests\Feature;

use App\Enums\UserRole;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\CreatesCargoData;
use Tests\TestCase;

class AuthApiTest extends TestCase
{
    use RefreshDatabase, CreatesCargoData;

    public function test_login_with_valid_credentials_returns_token(): void
    {
        $user = $this->makeUser(UserRole::Operator);

        $this->postJson('/api/login', ['email' => $user->email, 'password' => 'password'])
            ->assertOk()
            ->assertJsonStructure(['data' => ['token', 'user' => ['id', 'email', 'role']]])
            ->assertJsonPath('data.user.role', 'operator');
    }

    public function test_login_with_wrong_password_is_rejected(): void
    {
        $user = $this->makeUser(UserRole::Customer);

        $this->postJson('/api/login', ['email' => $user->email, 'password' => 'wrong-password'])
            ->assertUnauthorized();
    }

    public function test_inactive_user_cannot_login(): void
    {
        $user = $this->makeUser(UserRole::Customer, ['is_active' => false]);

        $this->postJson('/api/login', ['email' => $user->email, 'password' => 'password'])
            ->assertUnauthorized();
    }

    public function test_login_requires_email_and_password(): void
    {
        $this->postJson('/api/login', [])->assertUnprocessable()->assertJsonValidationErrors(['email', 'password']);
    }

    public function test_protected_endpoint_requires_authentication(): void
    {
        $this->getJson('/api/user')->assertUnauthorized();
    }

    public function test_authenticated_user_can_fetch_profile(): void
    {
        $user = $this->makeUser(UserRole::Admin);
        $token = $this->postJson('/api/login', ['email' => $user->email, 'password' => 'password'])->json('data.token');

        $this->withToken($token)->getJson('/api/user')
            ->assertOk()
            ->assertJsonPath('data.email', $user->email);
    }

    public function test_register_always_creates_a_customer_even_if_role_is_sent(): void
    {
        $this->postJson('/api/register', [
            'name' => 'New User',
            'email' => 'new@example.test',
            'password' => 'password123',
            'password_confirmation' => 'password123',
            'role' => 'admin', // must be ignored
        ])->assertCreated()->assertJsonPath('data.user.role', 'customer');

        $this->assertDatabaseHas('users', ['email' => 'new@example.test', 'role' => 'customer']);
    }

    public function test_logout_revokes_the_token(): void
    {
        $user = $this->makeUser(UserRole::Customer);
        $token = $this->postJson('/api/login', ['email' => $user->email, 'password' => 'password'])->json('data.token');

        $this->assertDatabaseCount('personal_access_tokens', 1);

        $this->withToken($token)->postJson('/api/logout')->assertOk();

        $this->assertDatabaseCount('personal_access_tokens', 0);
    }
}
