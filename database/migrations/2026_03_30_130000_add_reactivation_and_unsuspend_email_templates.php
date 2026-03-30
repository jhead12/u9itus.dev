<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        $templates = [
            [
                'key' => 'campaign_reactivated',
                'name' => 'Campaign Reactivated',
                'description' => 'Sent to a politician when an admin reactivates their paused campaign.',
                'category' => 'campaign',
                'available_variables' => json_encode(['{{campaign.title}}']),
            ],
            [
                'key' => 'account_unsuspended',
                'name' => 'Account Reactivated',
                'description' => 'Sent when an admin restores access to a suspended user account.',
                'category' => 'account',
                'available_variables' => json_encode(['{{user.name}}', '{{user.first_name}}', '{{user.email}}', '{{user.user_type}}']),
            ],
        ];

        foreach ($templates as $template) {
            DB::table('email_templates')->insertOrIgnore(array_merge($template, [
                'is_active' => true,
                'created_at' => now(),
                'updated_at' => now(),
            ]));
        }
    }

    public function down(): void
    {
        DB::table('email_templates')
            ->whereIn('key', ['campaign_reactivated', 'account_unsuspended'])
            ->delete();
    }
};
