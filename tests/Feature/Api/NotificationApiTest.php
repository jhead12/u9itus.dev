<?php

namespace Tests\Feature\Api;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Str;
use Tests\TestCase;

class NotificationApiTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        // Some environments don't register the Sanctum guard in tests.
        // Map auth:sanctum to session auth so API middleware remains testable.
        Config::set('auth.guards.sanctum', [
            'driver' => 'session',
            'provider' => 'users',
        ]);
    }

    public function test_notifications_index_returns_only_authenticated_users_notifications(): void
    {
        $user = User::factory()->create();
        $otherUser = User::factory()->create();

        $first = $this->createDatabaseNotification($user, [
            'message' => 'First user notification',
            'type' => 'low_balance',
        ]);

        $this->createDatabaseNotification($otherUser, [
            'message' => 'Other user notification',
            'type' => 'low_balance',
        ]);

        $this->actingAs($user, 'sanctum');

        $response = $this->getJson('/api/v1/notifications');

        $response->assertOk()
            ->assertJsonPath('data.0.id', $first->id)
            ->assertJsonMissing(['message' => 'Other user notification']);
    }

    public function test_unread_count_returns_expected_value(): void
    {
        $user = User::factory()->create();

        $this->createDatabaseNotification($user, ['message' => 'Unread A'], null);
        $this->createDatabaseNotification($user, ['message' => 'Unread B'], null);
        $this->createDatabaseNotification($user, ['message' => 'Read C'], now());

        $this->actingAs($user, 'sanctum');

        $response = $this->getJson('/api/v1/notifications/unread-count');

        $response->assertOk()
            ->assertJson(['count' => 2]);
    }

    public function test_mark_as_read_marks_only_target_notification(): void
    {
        $user = User::factory()->create();

        $target = $this->createDatabaseNotification($user, ['message' => 'Target unread'], null);
        $other = $this->createDatabaseNotification($user, ['message' => 'Other unread'], null);

        $this->actingAs($user, 'sanctum');

        $response = $this->postJson('/api/v1/notifications/' . $target->id . '/mark-as-read');

        $response->assertOk();

        $this->assertNotNull($target->fresh()->read_at);
        $this->assertNull($other->fresh()->read_at);
    }

    public function test_mark_all_as_read_marks_every_unread_notification(): void
    {
        $user = User::factory()->create();

        $first = $this->createDatabaseNotification($user, ['message' => 'Unread 1'], null);
        $second = $this->createDatabaseNotification($user, ['message' => 'Unread 2'], null);

        $this->actingAs($user, 'sanctum');

        $response = $this->postJson('/api/v1/notifications/mark-all-as-read');

        $response->assertOk();

        $this->assertNotNull($first->fresh()->read_at);
        $this->assertNotNull($second->fresh()->read_at);

        $countResponse = $this->getJson('/api/v1/notifications/unread-count');
        $countResponse->assertOk()->assertJson(['count' => 0]);
    }

    public function test_notification_endpoints_require_authentication(): void
    {
        $this->getJson('/api/v1/notifications')->assertStatus(401);
        $this->getJson('/api/v1/notifications/unread-count')->assertStatus(401);
    }

    private function createDatabaseNotification(User $user, array $data = [], $readAt = null)
    {
        return $user->notifications()->create([
            'id' => (string) Str::uuid(),
            'type' => 'App\\Notifications\\LowBalanceNotification',
            'data' => array_merge([
                'message' => 'Test notification',
                'type' => 'low_balance',
            ], $data),
            'read_at' => $readAt,
        ]);
    }
}
