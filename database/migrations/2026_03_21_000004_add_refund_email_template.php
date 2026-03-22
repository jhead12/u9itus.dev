<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        DB::table('email_templates')->insertOrIgnore([
            'key' => 'credits_refunded',
            'name' => 'Credits Refunded',
            'description' => 'Sent to a politician after an admin refund of unused credits is completed.',
            'category' => 'billing',
            'available_variables' => json_encode([
                '{{user.name}}',
                '{{amount}}',
                '{{refunded_credits}}',
                '{{new_balance}}',
                '{{transaction_id}}',
                '{{reason}}',
            ]),
            'is_active' => true,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    public function down(): void
    {
        DB::table('email_templates')
            ->where('key', 'credits_refunded')
            ->delete();
    }
};
