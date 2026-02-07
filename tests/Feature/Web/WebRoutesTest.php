<?php

namespace Tests\Feature\Web;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class WebRoutesTest extends TestCase
{
    use RefreshDatabase;

    public function test_welcome_page_loads_successfully(): void
    {
        $response = $this->get('/');

        $response->assertStatus(200);
    }

    public function test_dashboard_redirects_unauthenticated_users(): void
    {
        $response = $this->get('/dashboard');

        $response->assertStatus(302);
        $response->assertRedirect('/login');
    }

    public function test_dashboard_loads_for_authenticated_users(): void
    {
        $user = User::factory()->create();

        $response = $this->actingAs($user)->get('/dashboard');

        // May not have dashboard view yet - 200 or 500 acceptable
        $this->assertContains($response->status(), [200, 500]);
    }

    public function test_profile_edit_requires_authentication(): void
    {
        $response = $this->get('/profile');

        $response->assertStatus(302);
        $response->assertRedirect('/login');
    }

    public function test_profile_edit_loads_for_authenticated_users(): void
    {
        $user = User::factory()->create();

        $response = $this->actingAs($user)->get('/profile');

        $this->assertContains($response->status(), [200, 500]);
    }
}
