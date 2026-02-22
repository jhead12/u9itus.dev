<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Stores admin-editable metadata for every transactional email.
     * The `subject` and `preview_text` columns can override the defaults
     * baked into each Mailable class.  The `body_override` column lets an
     * admin paste a full HTML body that replaces the Blade view entirely —
     * leave it NULL to keep using the Blade template.
     */
    public function up(): void
    {
        Schema::create('email_templates', function (Blueprint $table) {
            $table->id();

            // Unique machine-readable key — matches the Mailable class short name
            $table->string('key')->unique();

            // Human-readable name shown in the admin UI
            $table->string('name');

            // Short description of when this email fires
            $table->string('description')->nullable();

            // Category grouping for the admin list
            $table->string('category')->default('general');

            // Overrideable subject line (NULL = use class default)
            $table->string('subject_override')->nullable();

            // Short preview text shown in email clients (optional)
            $table->string('preview_text')->nullable();

            // Full HTML body override (NULL = use Blade template)
            $table->longText('body_override')->nullable();

            // Available merge tags documented for admin reference
            $table->json('available_variables')->nullable();

            // Whether this notification is currently active
            $table->boolean('is_active')->default(true);

            // Who last edited this template
            $table->foreignId('last_edited_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
        });

        // ── Seed default template rows ─────────────────────────────────────
        $templates = [
            // ── Identity / KYC ────────────────────────────────────────────
            [
                'key'         => 'kyc_approved',
                'name'        => 'KYC Approved',
                'description' => 'Sent to a user when an admin approves their identity verification.',
                'category'    => 'kyc',
                'available_variables' => json_encode(['{{user.name}}', '{{user.first_name}}', '{{user.email}}', '{{user.user_type}}']),
            ],
            [
                'key'         => 'kyc_rejected',
                'name'        => 'KYC Rejected',
                'description' => 'Sent to a user when their identity verification is rejected, including the rejection reason.',
                'category'    => 'kyc',
                'available_variables' => json_encode(['{{user.name}}', '{{user.first_name}}', '{{user.email}}', '{{reason}}']),
            ],

            // ── Campaign ──────────────────────────────────────────────────
            [
                'key'         => 'campaign_approved',
                'name'        => 'Campaign Approved',
                'description' => 'Sent to a politician when their campaign is approved and set to active.',
                'category'    => 'campaign',
                'available_variables' => json_encode(['{{campaign.title}}', '{{campaign.governance_level}}', '{{campaign.target_state}}']),
            ],
            [
                'key'         => 'campaign_rejected',
                'name'        => 'Campaign Rejected',
                'description' => 'Sent to a politician when their campaign is rejected.',
                'category'    => 'campaign',
                'available_variables' => json_encode(['{{campaign.title}}', '{{reason}}']),
            ],
            [
                'key'         => 'campaign_completed',
                'name'        => 'Campaign Completed',
                'description' => 'Sent when a campaign exhausts all its view credits.',
                'category'    => 'campaign',
                'available_variables' => json_encode(['{{campaign.title}}', '{{total_views}}', '{{total_spent}}']),
            ],

            // ── Billing / Credits ─────────────────────────────────────────
            [
                'key'         => 'credits_purchased',
                'name'        => 'Credits Purchased',
                'description' => 'Sent to a politician after a successful credit purchase.',
                'category'    => 'billing',
                'available_variables' => json_encode(['{{user.name}}', '{{credits}}', '{{amount}}', '{{new_balance}}', '{{transaction_id}}']),
            ],
            [
                'key'         => 'low_balance_alert',
                'name'        => 'Low Balance Alert',
                'description' => 'Sent when a politician\'s credit balance falls below the alert threshold.',
                'category'    => 'billing',
                'available_variables' => json_encode(['{{user.name}}', '{{current_balance}}', '{{remaining_views}}', '{{campaign_title}}']),
            ],

            // ── Payouts ───────────────────────────────────────────────────
            [
                'key'         => 'payout_processed',
                'name'        => 'Payout Processed',
                'description' => 'Sent to a voter after a payout batch is processed and their earnings are sent.',
                'category'    => 'payout',
                'available_variables' => json_encode(['{{user.name}}', '{{amount}}', '{{view_count}}', '{{payout_method}}', '{{period_label}}']),
            ],

            // ── Account / Auth ────────────────────────────────────────────
            [
                'key'         => 'welcome',
                'name'        => 'Welcome Email',
                'description' => 'Sent immediately after a new user registers.',
                'category'    => 'account',
                'available_variables' => json_encode(['{{user.name}}', '{{user.email}}', '{{user.user_type}}']),
            ],
            [
                'key'         => 'admin_new_user',
                'name'        => 'Admin: New User Alert',
                'description' => 'Sent to admin(s) when a new user registers.',
                'category'    => 'admin',
                'available_variables' => json_encode(['{{user.name}}', '{{user.email}}', '{{user.user_type}}']),
            ],
        ];

        foreach ($templates as $template) {
            DB::table('email_templates')->insert(array_merge($template, [
                'is_active'  => true,
                'created_at' => now(),
                'updated_at' => now(),
            ]));
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('email_templates');
    }
};
