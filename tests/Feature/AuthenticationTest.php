<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class AuthenticationTest extends TestCase
{
    use RefreshDatabase;

    public function test_guests_are_redirected_to_login(): void
    {
        $this->get('/dashboard')->assertRedirect('/login');
        $this->get('/assignments/create')->assertRedirect('/login');
        $this->get('/bid-requests/create')->assertRedirect('/login');
    }

    public function test_login_page_renders(): void
    {
        $this->get('/login')->assertOk()->assertInertia(fn (Assert $page) => $page->component('Auth/Login'));
    }

    public function test_users_can_sign_in_with_username_case_insensitively(): void
    {
        $user = User::factory()->create(['username' => 'Adjuster1', 'password' => 'secret-pass']);

        $this->post('/login', ['username' => ' ADJUSTER1 ', 'password' => 'secret-pass'])
            ->assertRedirect('/dashboard');

        $this->assertAuthenticatedAs($user);
        $this->assertNotNull($user->fresh()->last_login_at);
    }

    public function test_wrong_password_is_rejected(): void
    {
        User::factory()->create(['username' => 'adjuster1', 'password' => 'secret-pass']);

        $this->from('/login')->post('/login', ['username' => 'adjuster1', 'password' => 'nope'])
            ->assertRedirect('/login')
            ->assertSessionHasErrors('username');

        $this->assertGuest();
    }

    public function test_inactive_users_cannot_sign_in(): void
    {
        User::factory()->inactive()->create(['username' => 'former', 'password' => 'secret-pass']);

        $this->post('/login', ['username' => 'former', 'password' => 'secret-pass'])->assertSessionHasErrors('username');

        $this->assertGuest();
    }

    public function test_repeated_failures_lock_the_username_out(): void
    {
        User::factory()->create(['username' => 'adjuster1', 'password' => 'secret-pass']);

        for ($i = 0; $i < 5; $i++) {
            $this->post('/login', ['username' => 'adjuster1', 'password' => 'wrong']);
        }

        $this->post('/login', ['username' => 'adjuster1', 'password' => 'secret-pass'])
            ->assertSessionHasErrors('username');

        $this->assertStringStartsWith('Too many sign-in attempts.', session('errors')->first('username'));
        $this->assertGuest();
    }

    public function test_users_can_sign_out(): void
    {
        $this->actingAs(User::factory()->create())->post('/logout')->assertRedirect('/login');

        $this->assertGuest();
    }

    public function test_dashboard_shows_counts_and_recent_items(): void
    {
        $user = User::factory()->create();
        $user->assignments()->create(['type' => 'standard', 'claim_number' => 'CLM-1', 'payload' => []]);

        $this->actingAs($user)->get('/dashboard')->assertInertia(fn (Assert $page) => $page
            ->component('Dashboard')
            ->where('stats.assignments', 1)
            ->where('stats.bidRequests', 0)
            ->has('recentAssignments', 1)
            ->where('auth.user.username', $user->username)
            ->missing('auth.user.password'));
    }
}
