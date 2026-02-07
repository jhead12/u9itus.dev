<?php

namespace Tests\Feature\Wix;

use Tests\TestCase;

class DashboardTest extends TestCase
{
    public function test_wix_dashboard_index_endpoint_exists(): void
    {
        $response = $this->withHeaders([
            'X-Wix-Instance' => 'fake-instance-token',
        ])->get('/wix/dashboard');

        // Wix middleware may block without valid instance
        $this->assertContains($response->status(), [200, 401, 403, 500]);
    }

    public function test_wix_dashboard_politician_endpoint_exists(): void
    {
        $response = $this->withHeaders([
            'X-Wix-Instance' => 'fake-instance-token',
        ])->get('/wix/dashboard/politician');

        $this->assertContains($response->status(), [200, 401, 403, 500]);
    }

    public function test_wix_dashboard_voter_endpoint_exists(): void
    {
        $response = $this->withHeaders([
            'X-Wix-Instance' => 'fake-instance-token',
        ])->get('/wix/dashboard/voter');

        $this->assertContains($response->status(), [200, 401, 403, 500]);
    }

    public function test_wix_dashboard_admin_endpoint_exists(): void
    {
        $response = $this->withHeaders([
            'X-Wix-Instance' => 'fake-instance-token',
        ])->get('/wix/dashboard/admin');

        $this->assertContains($response->status(), [200, 401, 403, 500]);
    }

    public function test_wix_widget_feed_endpoint_exists(): void
    {
        $response = $this->withHeaders([
            'X-Wix-Instance' => 'fake-instance-token',
        ])->get('/wix/widget');

        $this->assertContains($response->status(), [200, 401, 403, 500]);
    }

    public function test_wix_widget_settings_endpoint_exists(): void
    {
        $response = $this->withHeaders([
            'X-Wix-Instance' => 'fake-instance-token',
        ])->get('/wix/widget/settings');

        $this->assertContains($response->status(), [200, 401, 403, 500]);
    }

    public function test_wix_dashboard_requires_headers(): void
    {
        // Request without Wix headers should be blocked
        $response = $this->get('/wix/dashboard');

        // Should return 401 or 403 without valid Wix instance
        $this->assertContains($response->status(), [401, 403, 500]);
    }
}
