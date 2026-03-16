<?php

namespace Database\Seeders;

use App\Enums\ApprovalStatus;
use App\Enums\CampaignStatus;
use App\Enums\CampaignType;
use App\Enums\PaymentStatus;
use App\Models\PoliticalCampaign;
use App\Models\Politician;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

class PoliticianCampaignSeeder extends Seeder
{
    /**
     * Seed a realistic set of politicians — each with their own user account,
     * published profile page, video links, and multiple campaigns.
     */
    public function run(): void
    {
        // Ensure roles exist before we assign them
        $this->call(RoleSeeder::class);

        $politicians = $this->politicianData();

        foreach ($politicians as $data) {
            // Create (or find) the user account for this politician
            $user = User::firstOrCreate(
                ['email' => $data['email']],
                [
                    'name'              => $data['full_name'],
                    'password'          => Hash::make('password'),
                    'email_verified_at' => now(),
                ]
            );

            // Assign the politician role if the Spatie roles table exists
            if (method_exists($user, 'assignRole')) {
                $user->assignRole('politician');
            }

            // Create (or find) the Politician record
            $politician = Politician::firstOrCreate(
                ['user_id' => $user->id],
                [
                    'full_name'          => $data['full_name'],
                    'political_office'   => $data['office'],
                    'governance_level'   => $data['governance_level'],
                    'district'           => $data['district'] ?? null,
                    'party_affiliation'  => $data['party'],
                    'state'              => $data['state'],
                    'city'               => $data['city'],
                    'website_url'        => $data['website_url'] ?? null,
                    'bio'                => $data['bio'],
                    'verified_official'  => true,
                    'kyc_status'         => 'approved',
                    'is_active'          => true,
                    'page_published'     => true,
                    'video_links'        => $data['video_links'] ?? [],
                ]
            );

            // Seed campaigns for this politician
            foreach ($data['campaigns'] as $campaign) {
                PoliticalCampaign::firstOrCreate(
                    [
                        'politician_id' => $politician->id,
                        'title'         => $campaign['title'],
                    ],
                    [
                        'uuid'                        => Str::uuid(),
                        'message_summary'             => $campaign['message_summary'],
                        'campaign_type'               => CampaignType::Video->value,
                        'governance_level'            => $politician->governance_level,
                        'media_url'                   => $campaign['media_url'],
                        'media_duration'              => $campaign['duration'] ?? 60,
                        'thumbnail_url'               => $campaign['thumbnail_url'] ?? null,
                        'revenue_per_view'            => $campaign['revenue_per_view'] ?? 0.60,
                        'voter_payout_per_view'       => $campaign['voter_payout'] ?? 0.25,
                        'total_budget'                => $campaign['budget'] ?? 1000.00,
                        'amount_spent'                => $campaign['spent'] ?? 0.00,
                        'head_enterprises_fee_percent'=> 15.00,
                        'total_views_requested'       => $campaign['views_requested'] ?? 1000,
                        'views_completed'             => $campaign['views_completed'] ?? 0,
                        'target_states'               => $campaign['target_states'] ?? null,
                        'min_watch_time_percent'      => 80,
                        'status'                      => $campaign['status'] ?? CampaignStatus::Active->value,
                        'approval_status'             => $campaign['approval_status'] ?? ApprovalStatus::Approved->value,
                        'payment_status'              => $campaign['payment_status'] ?? PaymentStatus::Captured->value,
                        'approved_at'                 => $campaign['approved_at'] ?? now()->subDays(rand(5, 30)),
                        'started_at'                  => $campaign['started_at'] ?? now()->subDays(rand(1, 10)),
                        'completed_at'                => $campaign['completed_at'] ?? null,
                    ]
                );
            }

            $this->command->info("  Seeded: {$data['full_name']} ({$politician->id}) — " . count($data['campaigns']) . ' campaigns');
        }
    }

    // ──────────────────────────────────────────────────────────────────────────
    // Seed data
    // ──────────────────────────────────────────────────────────────────────────

    private function politicianData(): array
    {
        return [
            // ── 1. Federal – Senator ─────────────────────────────────────────
            [
                'full_name'   => 'Marcus J. Holloway',
                'email'       => 'm.holloway@seed.u9itus.test',
                'office'      => 'U.S. Senator',
                'governance_level' => 'federal',
                'party'       => 'Democrat',
                'state'       => 'GA',
                'city'        => 'Atlanta',
                'website_url' => 'https://www.senate.gov',
                'bio'         => 'Senator Marcus Holloway has represented Georgia in the United States Senate for over a decade, championing affordable healthcare, clean energy, and veterans\' rights. A former prosecutor, he brings a law-and-order perspective balanced with compassion for working families.',
                'video_links' => [
                    ['url' => 'https://www.youtube.com/watch?v=dQw4w9WgXcQ', 'title' => 'State of the Union Response Address'],
                    ['url' => 'https://www.c-span.org/search/?searchtype=Videos&query=Marcus+Holloway', 'title' => 'C-SPAN Appearances'],
                ],
                'campaigns' => [
                    [
                        'title'           => 'Healthcare for Every Georgian',
                        'message_summary' => 'Senator Holloway outlines his plan to expand Medicaid coverage to over 400,000 uninsured Georgians through bipartisan legislation.',
                        'media_url'       => 'https://www.youtube.com/watch?v=dQw4w9WgXcQ',
                        'duration'        => 90,
                        'budget'          => 5000.00,
                        'spent'           => 3120.00,
                        'views_requested' => 5000,
                        'views_completed' => 3200,
                        'revenue_per_view'=> 0.75,
                        'voter_payout'    => 0.30,
                        'target_states'   => ['GA'],
                        'status'          => CampaignStatus::Active->value,
                        'approval_status' => ApprovalStatus::Approved->value,
                        'payment_status'  => PaymentStatus::Captured->value,
                        'approved_at'     => now()->subDays(14),
                        'started_at'      => now()->subDays(10),
                    ],
                    [
                        'title'           => 'Clean Energy Jobs for Georgia',
                        'message_summary' => 'New federal investment in solar and wind projects will create 12,000 jobs in Georgia over the next four years.',
                        'media_url'       => 'https://www.youtube.com/watch?v=dQw4w9WgXcQ',
                        'duration'        => 60,
                        'budget'          => 3000.00,
                        'spent'           => 3000.00,
                        'views_requested' => 3000,
                        'views_completed' => 3000,
                        'status'          => CampaignStatus::Completed->value,
                        'approval_status' => ApprovalStatus::Approved->value,
                        'payment_status'  => PaymentStatus::Captured->value,
                        'approved_at'     => now()->subDays(60),
                        'started_at'      => now()->subDays(55),
                        'completed_at'    => now()->subDays(10),
                    ],
                    [
                        'title'           => 'Veterans First — Expanding VA Benefits',
                        'message_summary' => 'Senator Holloway\'s bill would fully fund veterans\' mental health services and shorten VA appointment wait times by 30%.',
                        'media_url'       => 'https://www.youtube.com/watch?v=dQw4w9WgXcQ',
                        'duration'        => 45,
                        'budget'          => 2000.00,
                        'spent'           => 0.00,
                        'views_requested' => 2000,
                        'views_completed' => 0,
                        'status'          => CampaignStatus::Draft->value,
                        'approval_status' => ApprovalStatus::Pending->value,
                        'payment_status'  => PaymentStatus::Pending->value,
                        'approved_at'     => null,
                        'started_at'      => null,
                    ],
                ],
            ],

            // ── 2. State – Representative ────────────────────────────────────
            [
                'full_name'   => 'Diana Reyes',
                'email'       => 'd.reyes@seed.u9itus.test',
                'office'      => 'State Representative',
                'governance_level' => 'state',
                'district'    => 'District 14',
                'party'       => 'Republican',
                'state'       => 'TX',
                'city'        => 'Houston',
                'website_url' => 'https://house.texas.gov',
                'bio'         => 'Representative Diana Reyes has served Texas\'s 14th district for six years, fighting for border security, small business tax relief, and parental rights in education. A second-generation Texan and small-business owner, she understands the challenges facing everyday families.',
                'video_links' => [
                    ['url' => 'https://www.youtube.com/watch?v=dQw4w9WgXcQ', 'title' => 'House Floor Speech on Border Security'],
                ],
                'campaigns' => [
                    [
                        'title'           => 'Protect Texas Families — Border Security Now',
                        'message_summary' => 'Rep. Reyes calls for immediate federal action on border enforcement, asking voters to contact their U.S. representatives in support of SB 4.',
                        'media_url'       => 'https://www.youtube.com/watch?v=dQw4w9WgXcQ',
                        'duration'        => 75,
                        'budget'          => 2500.00,
                        'spent'           => 1800.00,
                        'views_requested' => 2500,
                        'views_completed' => 1820,
                        'revenue_per_view'=> 0.65,
                        'voter_payout'    => 0.28,
                        'target_states'   => ['TX'],
                        'status'          => CampaignStatus::Active->value,
                        'approval_status' => ApprovalStatus::Approved->value,
                        'payment_status'  => PaymentStatus::Captured->value,
                        'approved_at'     => now()->subDays(20),
                        'started_at'      => now()->subDays(18),
                    ],
                    [
                        'title'           => 'Small Business Tax Relief Act',
                        'message_summary' => 'Diana Reyes is championing legislation that cuts the state franchise tax for businesses with under 50 employees, putting more money back into local communities.',
                        'media_url'       => 'https://www.youtube.com/watch?v=dQw4w9WgXcQ',
                        'duration'        => 60,
                        'budget'          => 1500.00,
                        'spent'           => 1500.00,
                        'views_requested' => 1500,
                        'views_completed' => 1500,
                        'status'          => CampaignStatus::Completed->value,
                        'approval_status' => ApprovalStatus::Approved->value,
                        'payment_status'  => PaymentStatus::Captured->value,
                        'approved_at'     => now()->subDays(90),
                        'started_at'      => now()->subDays(85),
                        'completed_at'    => now()->subDays(30),
                    ],
                ],
            ],

            // ── 3. Local – Mayor ─────────────────────────────────────────────
            [
                'full_name'   => 'Jerome T. Wallace',
                'email'       => 'j.wallace@seed.u9itus.test',
                'office'      => 'Mayor',
                'governance_level' => 'local',
                'party'       => 'Independent',
                'state'       => 'OH',
                'city'        => 'Columbus',
                'website_url' => null,
                'bio'         => 'Mayor Jerome Wallace is in his second term leading Columbus, Ohio. A lifelong Columbus resident, he has focused on reducing violent crime by 18%, attracting $2 billion in new downtown investment, and building 3,000 affordable housing units since taking office.',
                'video_links' => [
                    ['url' => 'https://www.youtube.com/watch?v=dQw4w9WgXcQ', 'title' => 'State of the City Address 2025'],
                    ['url' => 'https://www.youtube.com/watch?v=dQw4w9WgXcQ', 'title' => 'Affordable Housing Press Conference'],
                ],
                'campaigns' => [
                    [
                        'title'           => 'Safe Streets Columbus Initiative',
                        'message_summary' => 'Mayor Wallace announces the Safe Streets program — 50 new officers, community liaison teams in every district, and expanded youth mentorship funding.',
                        'media_url'       => 'https://www.youtube.com/watch?v=dQw4w9WgXcQ',
                        'duration'        => 90,
                        'budget'          => 800.00,
                        'spent'           => 640.00,
                        'views_requested' => 800,
                        'views_completed' => 645,
                        'revenue_per_view'=> 0.60,
                        'voter_payout'    => 0.25,
                        'target_states'   => ['OH'],
                        'status'          => CampaignStatus::Active->value,
                        'approval_status' => ApprovalStatus::Approved->value,
                        'payment_status'  => PaymentStatus::Captured->value,
                        'approved_at'     => now()->subDays(8),
                        'started_at'      => now()->subDays(7),
                    ],
                    [
                        'title'           => '3,000 Homes — Columbus Affordable Housing Plan',
                        'message_summary' => 'Learn how the city\'s partnership with private developers is turning vacant lots into thriving, affordable neighborhoods for working families.',
                        'media_url'       => 'https://www.youtube.com/watch?v=dQw4w9WgXcQ',
                        'duration'        => 60,
                        'budget'          => 600.00,
                        'spent'           => 600.00,
                        'views_requested' => 600,
                        'views_completed' => 598,
                        'status'          => CampaignStatus::Completed->value,
                        'approval_status' => ApprovalStatus::Approved->value,
                        'payment_status'  => PaymentStatus::Captured->value,
                        'approved_at'     => now()->subDays(120),
                        'started_at'      => now()->subDays(115),
                        'completed_at'    => now()->subDays(40),
                    ],
                ],
            ],

            // ── 4. Federal – U.S. Representative ─────────────────────────────
            [
                'full_name'   => 'Priya Sharma',
                'email'       => 'p.sharma@seed.u9itus.test',
                'office'      => 'U.S. Representative',
                'governance_level' => 'federal',
                'district'    => 'District 8',
                'party'       => 'Democrat',
                'state'       => 'CA',
                'city'        => 'San Jose',
                'website_url' => 'https://www.congress.gov',
                'bio'         => 'Representative Priya Sharma is a first-generation immigrant and former tech executive who now serves California\'s 8th Congressional District. She leads on issues of technology regulation, STEM education funding, and immigration reform.',
                'video_links' => [
                    ['url' => 'https://www.youtube.com/watch?v=dQw4w9WgXcQ', 'title' => 'Tech Accountability Hearing Opening Statement'],
                    ['url' => 'https://www.c-span.org/search/?searchtype=Videos&query=Priya+Sharma+Congress', 'title' => 'C-SPAN Congressional Coverage'],
                ],
                'campaigns' => [
                    [
                        'title'           => 'AI Safety & Accountability Act',
                        'message_summary' => 'Rep. Sharma introduces landmark legislation requiring AI companies to conduct independent audits and disclose training data sources to protect consumers.',
                        'media_url'       => 'https://www.youtube.com/watch?v=dQw4w9WgXcQ',
                        'duration'        => 120,
                        'budget'          => 4000.00,
                        'spent'           => 2200.00,
                        'views_requested' => 4000,
                        'views_completed' => 2210,
                        'revenue_per_view'=> 0.80,
                        'voter_payout'    => 0.32,
                        'target_states'   => ['CA'],
                        'status'          => CampaignStatus::Active->value,
                        'approval_status' => ApprovalStatus::Approved->value,
                        'payment_status'  => PaymentStatus::Captured->value,
                        'approved_at'     => now()->subDays(12),
                        'started_at'      => now()->subDays(10),
                    ],
                    [
                        'title'           => 'STEM Scholarships for Every Student',
                        'message_summary' => 'Sharma\'s education bill will fund 50,000 new STEM scholarships annually for students in underserved communities, bridging the opportunity gap in tech.',
                        'media_url'       => 'https://www.youtube.com/watch?v=dQw4w9WgXcQ',
                        'duration'        => 60,
                        'budget'          => 2000.00,
                        'spent'           => 950.00,
                        'views_requested' => 2000,
                        'views_completed' => 960,
                        'status'          => CampaignStatus::Active->value,
                        'approval_status' => ApprovalStatus::Approved->value,
                        'payment_status'  => PaymentStatus::Captured->value,
                        'approved_at'     => now()->subDays(5),
                        'started_at'      => now()->subDays(4),
                    ],
                    [
                        'title'           => 'Path to Citizenship Reform',
                        'message_summary' => 'A commonsense immigration reform bill that creates a clear, fair path to citizenship for the 11 million undocumented people already contributing to our economy.',
                        'media_url'       => 'https://www.youtube.com/watch?v=dQw4w9WgXcQ',
                        'duration'        => 90,
                        'budget'          => 3500.00,
                        'spent'           => 0.00,
                        'views_requested' => 3500,
                        'views_completed' => 0,
                        'status'          => CampaignStatus::Draft->value,
                        'approval_status' => ApprovalStatus::Pending->value,
                        'payment_status'  => PaymentStatus::Pending->value,
                        'approved_at'     => null,
                        'started_at'      => null,
                    ],
                ],
            ],

            // ── 5. State – Governor ──────────────────────────────────────────
            [
                'full_name'   => 'Robert "Bob" Ashford',
                'email'       => 'r.ashford@seed.u9itus.test',
                'office'      => 'Governor',
                'governance_level' => 'state',
                'party'       => 'Republican',
                'state'       => 'FL',
                'city'        => 'Tallahassee',
                'website_url' => 'https://www.flgov.com',
                'bio'         => 'Governor Bob Ashford is Florida\'s 46th Governor, known for championing school choice, limiting state regulation on businesses, and his aggressive hurricane preparedness programs. Before entering politics he spent 20 years as a construction entrepreneur.',
                'video_links' => [
                    ['url' => 'https://www.youtube.com/watch?v=dQw4w9WgXcQ', 'title' => 'Annual State of Florida Address'],
                ],
                'campaigns' => [
                    [
                        'title'           => 'Florida School Choice Expansion',
                        'message_summary' => 'Governor Ashford signs legislation giving every Florida family access to $7,000 per-student education vouchers, breaking the zip-code barrier in education.',
                        'media_url'       => 'https://www.youtube.com/watch?v=dQw4w9WgXcQ',
                        'duration'        => 75,
                        'budget'          => 6000.00,
                        'spent'           => 4500.00,
                        'views_requested' => 6000,
                        'views_completed' => 4510,
                        'revenue_per_view'=> 0.70,
                        'voter_payout'    => 0.28,
                        'target_states'   => ['FL'],
                        'status'          => CampaignStatus::Active->value,
                        'approval_status' => ApprovalStatus::Approved->value,
                        'payment_status'  => PaymentStatus::Captured->value,
                        'approved_at'     => now()->subDays(25),
                        'started_at'      => now()->subDays(22),
                    ],
                    [
                        'title'           => 'Cutting Regulations — Florida Open for Business',
                        'message_summary' => 'The Governor\'s deregulation package has eliminated over 300 unnecessary state rules, ranked Florida #1 in business climate for the third consecutive year.',
                        'media_url'       => 'https://www.youtube.com/watch?v=dQw4w9WgXcQ',
                        'duration'        => 60,
                        'budget'          => 3000.00,
                        'spent'           => 3000.00,
                        'views_requested' => 3000,
                        'views_completed' => 3000,
                        'status'          => CampaignStatus::Completed->value,
                        'approval_status' => ApprovalStatus::Approved->value,
                        'payment_status'  => PaymentStatus::Captured->value,
                        'approved_at'     => now()->subDays(180),
                        'started_at'      => now()->subDays(175),
                        'completed_at'    => now()->subDays(90),
                    ],
                ],
            ],
        ];
    }
}
