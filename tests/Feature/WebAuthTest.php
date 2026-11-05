<?php

namespace Tests\Feature;

use App\Enums\UserRole;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\CreatesCargoData;
use Tests\TestCase;

class WebAuthTest extends TestCase
{
    use RefreshDatabase, CreatesCargoData;

    public function test_guest_is_redirected_from_dashboard_to_login(): void
    {
        $this->get('/dashboard')->assertRedirect('/login');
    }

    public function test_user_can_login_and_see_dashboard(): void
    {
        $user = $this->makeUser(UserRole::Operator);

        $this->post('/login', ['email' => $user->email, 'password' => 'password'])
            ->assertRedirect('/dashboard');

        $this->get('/dashboard')->assertOk()->assertSee($user->name);
    }

    public function test_invalid_login_shows_error(): void
    {
        $user = $this->makeUser(UserRole::Customer);

        $this->from('/login')
            ->post('/login', ['email' => $user->email, 'password' => 'nope'])
            ->assertRedirect('/login')
            ->assertSessionHasErrors('email');

        $this->assertGuest();
    }

    public function test_logout_ends_the_session(): void
    {
        $user = $this->makeUser(UserRole::Customer);

        $this->actingAs($user)->post('/logout')->assertRedirect('/login');

        $this->assertGuest();
    }
}
