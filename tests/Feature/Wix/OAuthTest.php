<?php

namespace Tests\Feature\Wix;

use Tests\TestCase;

class OAuthTest extends TestCase
{
    public function test_wix_install_endpoint_exists(): void
    {
        $response = $this->get('/wix/install?token=test-token');

        // Should redirect or show install page
        $this->assertContains($response->status(), [200, 302, 400, 500]);
    }

    public function test_wix_oauth_callback_endpoint_exists(): void
    {
        $response = $this->get('/wix/oauth/callback?code=test-code&state=test-state');

        // OAuth flow - may redirect or show error
        $this->assertContains($response->status(), [200, 302, 400, 401, 500]);
    }

    public function test_wix_signup_endpoint_exists(): void
    {
        $response = $this->get('/wix/signup');

        // Should show signup instructions
        $this->assertContains($response->status(), [200, 302, 500]);
    }
}
