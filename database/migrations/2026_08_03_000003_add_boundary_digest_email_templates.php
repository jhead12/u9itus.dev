<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        DB::table('email_templates')->insertOrIgnore([
            [
                'key' => 'boundary_digest',
                'name' => 'Weekly Saved Places Digest',
                'description' => 'Weekly email summarizing new candidate activity (news, endorsements) for the districts/cities a voter has favorited on the map.',
                'category' => 'engagement',
                'available_variables' => json_encode([
                    '{{voter.full_name}}',
                ]),
                'is_active' => true,
                'created_at' => now(),
                'updated_at' => now(),
            ],
            [
                'key' => 'guest_digest_confirmation',
                'name' => 'Guest Saved-Places Digest — Confirm Email',
                'description' => 'Sent when a guest (not logged in) opts in to weekly saved-places updates, asking them to confirm their email before any digest is sent.',
                'category' => 'engagement',
                'available_variables' => json_encode([
                    '{{voter.full_name}}',
                ]),
                'is_active' => true,
                'created_at' => now(),
                'updated_at' => now(),
            ],
        ]);
    }

    public function down(): void
    {
        DB::table('email_templates')
            ->whereIn('key', ['boundary_digest', 'guest_digest_confirmation'])
            ->delete();
    }
};
