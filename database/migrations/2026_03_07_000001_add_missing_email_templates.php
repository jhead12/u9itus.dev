<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Insert the three email-template rows that exist as Mail classes and Blade
     * views but were omitted from the original seed migration:
     *   - admin_password_reset
     *   - admin_account_created
     *   - profile_verification
     */
    public function up(): void
    {
        $templates = [
            [
                'key'                 => 'admin_password_reset',
                'name'                => 'Admin: Password Reset',
                'description'         => 'Sent to an admin user after their password has been reset.',
                'category'            => 'admin',
                'available_variables' => json_encode(['{{admin.name}}', '{{admin.email}}']),
            ],
            [
                'key'                 => 'admin_account_created',
                'name'                => 'Admin: Account Created / Updated',
                'description'         => 'Sent to a newly created (or updated) admin with their account details and temporary password.',
                'category'            => 'admin',
                'available_variables' => json_encode(['{{admin.name}}', '{{admin.email}}', '{{is_new}}', '{{temp_pass}}']),
            ],
            [
                'key'                 => 'profile_verification',
                'name'                => 'Politician Profile Verification',
                'description'         => 'Sent to a politician with a link to verify their political profile.',
                'category'            => 'account',
                'available_variables' => json_encode(['{{politician_name}}', '{{verification_url}}', '{{expiry_hours}}']),
            ],
        ];

        foreach ($templates as $template) {
            // Use insertOrIgnore so re-running is safe if a record already exists.
            DB::table('email_templates')->insertOrIgnore(array_merge($template, [
                'is_active'  => true,
                'created_at' => now(),
                'updated_at' => now(),
            ]));
        }
    }

    public function down(): void
    {
        DB::table('email_templates')
            ->whereIn('key', ['admin_password_reset', 'admin_account_created', 'profile_verification'])
            ->delete();
    }
};
