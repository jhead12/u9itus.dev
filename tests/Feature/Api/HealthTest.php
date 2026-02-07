<?php

namespace Tests\Feature\Api;

use Tests\TestCase;

class HealthTest extends TestCase
{
    public function test_health_endpoint_returns_ok_status(): void
    {
        $response = $this->getJson('/api/health');

        $response->assertStatus(200)
            ->assertJson([
                'status' => 'ok',
                'message' => 'Dial4Dough API is running',
            ])
            ->assertJsonStructure([
                'status',
                'message',
                'timestamp',
            ]);
    }

    public function test_health_endpoint_returns_iso8601_timestamp(): void
    {
        $response = $this->getJson('/api/health');

        $data = $response->json();
        $this->assertMatchesRegularExpression(
            '/^\d{4}-\d{2}-\d{2}T\d{2}:\d{2}:\d{2}/',
            $data['timestamp']
        );
    }
}
