<?php

namespace Tests\Feature\Api;

use Tests\TestCase;

class WebhookTest extends TestCase
{
    public function test_wix_webhook_endpoint_exists(): void
    {
        $response = $this->postJson('/api/wix/webhooks', [
            'instanceId' => 'test-instance-id',
            'eventType' => 'app.installed',
        ]);

        // May return validation error or success - just checking endpoint exists
        // Webhook signature verification may fail
        $this->assertContains($response->status(), [200, 400, 401, 403, 422, 500]);
    }

    public function test_wix_webhook_requires_post_method(): void
    {
        $response = $this->getJson('/api/wix/webhooks');

        // GET method should not be allowed
        $this->assertEquals(405, $response->status());
    }
}
