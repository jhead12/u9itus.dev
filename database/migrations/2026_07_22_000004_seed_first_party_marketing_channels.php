<?php

use App\Enums\ChannelStatus;
use App\Enums\ChannelType;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

/**
 * Seeds the first-party `email` marketing channel into marketing_channels.
 *
 * A data-migration (not a standalone seeder) so `migrate:fresh` / RefreshDatabase
 * in tests materializes the row alongside the schema — ChannelRegistry can then
 * resolve `email` → EmailChannel without a separate seed step. Idempotent: only
 * inserts when the key is absent so re-running the migration is a no-op.
 *
 * Third-party marketplace plugins do NOT seed here — they register through the
 * admin approval queue (status=pending → active) and carry a webhook config
 * instead of an in-repo provider_class.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (DB::table('marketing_channels')->where('key', 'email')->exists()) {
            return;
        }

        $class = config('u9itus.marketing.first_party_channels.email');

        DB::table('marketing_channels')->insert([
            'uuid'            => (string) Str::uuid(),
            'key'             => 'email',
            'label'           => 'Email',
            'channel_type'    => ChannelType::Email->value,
            'provider_class'  => $class,
            'is_first_party'  => true,
            'status'          => ChannelStatus::Active->value,
            'config'          => json_encode([]),
            'description'     => 'First-party email channel via the platform transactional mail driver.',
            'created_at'      => now(),
            'updated_at'      => now(),
        ]);
    }

    public function down(): void
    {
        DB::table('marketing_channels')->where('key', 'email')->delete();
    }
};