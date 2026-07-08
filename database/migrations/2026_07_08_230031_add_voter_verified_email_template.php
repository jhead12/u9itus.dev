<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        DB::table('email_templates')->insertOrIgnore([
            'key' => 'voter_verified',
            'name' => 'Voter Identity Verified',
            'description' => 'Sent to a voter when their Authentic User Verifier (Stripe Connect) identity verification completes and their account becomes active.',
            'category' => 'account',
            'available_variables' => json_encode([
                '{{voter.full_name}}',
                '{{voter.uuid}}',
            ]),
            'is_active' => true,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    public function down(): void
    {
        DB::table('email_templates')
            ->where('key', 'voter_verified')
            ->delete();
    }
};
