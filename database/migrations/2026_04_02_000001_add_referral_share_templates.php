<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Seed three admin-editable referral/share template rows.
     *
     * These templates drive the default copy shown in the sticky referral
     * toolbar (public politician profile) and the voter/politician referral
     * dashboard pages.  Subject_override → email/native-share title.
     * Body_override → primary share message text (plain-text, not HTML email).
     */
    public function up(): void
    {
        $templates = [
            [
                'key'                 => 'referral_profile_share',
                'name'                => 'Referral: Public Profile Share',
                'description'         => 'Message shown when a voter shares an unverified politician\'s public profile with their referral link.',
                'category'            => 'referral',
                'subject_override'    => null,
                'preview_text'        => 'Default title and share message for the public profile referral toolbar.',
                'body_override'       => 'Take a look at {{politician.name}}\'s U9itus profile. If they join or claim the page, please use my referral link.',
                'available_variables' => json_encode([
                    '{{politician.name}}',
                    '{{referral_code}}',
                    '{{referral_link}}',
                    '{{platform_name}}',
                ]),
            ],
            [
                'key'                 => 'referral_voter_share',
                'name'                => 'Referral: Voter Signup Share',
                'description'         => 'Message used when a user shares their voter referral link to recruit new voters.',
                'category'            => 'referral',
                'subject_override'    => null,
                'preview_text'        => 'Default title and share message for voter referral links.',
                'body_override'       => 'Join U9itus as a voter using my referral link and start participating on the platform.',
                'available_variables' => json_encode([
                    '{{referral_code}}',
                    '{{referral_link}}',
                    '{{platform_name}}',
                ]),
            ],
            [
                'key'                 => 'referral_politician_share',
                'name'                => 'Referral: Politician Signup Share',
                'description'         => 'Message used when a user shares their politician referral link to recruit politicians.',
                'category'            => 'referral',
                'subject_override'    => null,
                'preview_text'        => 'Default title and share message for politician referral links.',
                'body_override'       => 'Join U9itus as a politician using my referral link and launch your campaign presence on the platform.',
                'available_variables' => json_encode([
                    '{{referral_code}}',
                    '{{referral_link}}',
                    '{{platform_name}}',
                ]),
            ],
        ];

        foreach ($templates as $template) {
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
            ->whereIn('key', ['referral_profile_share', 'referral_voter_share', 'referral_politician_share'])
            ->delete();
    }
};
