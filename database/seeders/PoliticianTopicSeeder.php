<?php

namespace Database\Seeders;

use App\Models\PoliticianTopic;
use Illuminate\Database\Seeder;

class PoliticianTopicSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $topics = [
            ['name' => 'Healthcare', 'slug' => 'healthcare', 'icon' => '🏥', 'sort_order' => 1],
            ['name' => 'Education', 'slug' => 'education', 'icon' => '🎓', 'sort_order' => 2],
            ['name' => 'Climate Action', 'slug' => 'climate-action', 'icon' => '🌍', 'sort_order' => 3],
            ['name' => 'Jobs & Economy', 'slug' => 'jobs-economy', 'icon' => '💼', 'sort_order' => 4],
            ['name' => 'Housing', 'slug' => 'housing', 'icon' => '🏠', 'sort_order' => 5],
            ['name' => 'Public Safety', 'slug' => 'public-safety', 'icon' => '🛡️', 'sort_order' => 6],
            ['name' => 'Transportation', 'slug' => 'transportation', 'icon' => '🚗', 'sort_order' => 7],
            ['name' => 'Infrastructure', 'slug' => 'infrastructure', 'icon' => '🏗️', 'sort_order' => 8],
            ['name' => 'Tax Policy', 'slug' => 'tax-policy', 'icon' => '💰', 'sort_order' => 9],
            ['name' => 'Voting Rights', 'slug' => 'voting-rights', 'icon' => '🗳️', 'sort_order' => 10],
            ['name' => 'Small Business', 'slug' => 'small-business', 'icon' => '🏪', 'sort_order' => 11],
            ['name' => 'Immigration', 'slug' => 'immigration', 'icon' => '🌐', 'sort_order' => 12],
            ['name' => 'Environment', 'slug' => 'environment', 'icon' => '🌱', 'sort_order' => 13],
            ['name' => 'Cybersecurity', 'slug' => 'cybersecurity', 'icon' => '🔒', 'sort_order' => 14],
            ['name' => 'Agriculture', 'slug' => 'agriculture', 'icon' => '🌾', 'sort_order' => 15],
            ['name' => 'Healthcare Access', 'slug' => 'healthcare-access', 'icon' => '⚕️', 'sort_order' => 16],
            ['name' => 'Affordable Housing', 'slug' => 'affordable-housing', 'icon' => '🏘️', 'sort_order' => 17],
            ['name' => 'Gun Control', 'slug' => 'gun-control', 'icon' => '🎯', 'sort_order' => 18],
            ['name' => 'Democracy', 'slug' => 'democracy', 'icon' => '🤝', 'sort_order' => 19],
            ['name' => 'Student Debt', 'slug' => 'student-debt', 'icon' => '📚', 'sort_order' => 20],
            ['name' => 'Foreign Policy', 'slug' => 'foreign-policy', 'icon' => '🌎', 'sort_order' => 21],
            ['name' => 'National Security', 'slug' => 'national-security', 'icon' => '🎖️', 'sort_order' => 22],
            ['name' => 'Criminal Justice Reform', 'slug' => 'criminal-justice-reform', 'icon' => '⚖️', 'sort_order' => 23],
            ['name' => 'Veterans Affairs', 'slug' => 'veterans-affairs', 'icon' => '🫡', 'sort_order' => 24],
            ['name' => 'Social Security', 'slug' => 'social-security', 'icon' => '👵', 'sort_order' => 25],
            ['name' => 'Medicare', 'slug' => 'medicare', 'icon' => '🩺', 'sort_order' => 26],
            ['name' => 'Minimum Wage', 'slug' => 'minimum-wage', 'icon' => '💵', 'sort_order' => 27],
            ['name' => 'Labor Rights', 'slug' => 'labor-rights', 'icon' => '🛠️', 'sort_order' => 28],
            ['name' => 'LGBTQ+ Rights', 'slug' => 'lgbtq-rights', 'icon' => '🏳️‍🌈', 'sort_order' => 29],
            ['name' => 'Reproductive Rights', 'slug' => 'reproductive-rights', 'icon' => '🤰', 'sort_order' => 30],
            ['name' => 'Racial Justice', 'slug' => 'racial-justice', 'icon' => '✊', 'sort_order' => 31],
            ['name' => 'Disability Rights', 'slug' => 'disability-rights', 'icon' => '♿', 'sort_order' => 32],
            ['name' => 'Mental Health', 'slug' => 'mental-health', 'icon' => '🧠', 'sort_order' => 33],
            ['name' => 'Opioid & Addiction Crisis', 'slug' => 'opioid-addiction-crisis', 'icon' => '💊', 'sort_order' => 34],
            ['name' => 'Energy Policy', 'slug' => 'energy-policy', 'icon' => '⚡', 'sort_order' => 35],
            ['name' => 'Trade Policy', 'slug' => 'trade-policy', 'icon' => '🚢', 'sort_order' => 36],
            ['name' => 'Technology & AI Policy', 'slug' => 'technology-ai-policy', 'icon' => '🤖', 'sort_order' => 37],
            ['name' => 'Privacy Rights', 'slug' => 'privacy-rights', 'icon' => '🔐', 'sort_order' => 38],
            ['name' => 'Campaign Finance Reform', 'slug' => 'campaign-finance-reform', 'icon' => '💸', 'sort_order' => 39],
            ['name' => 'Government Ethics & Accountability', 'slug' => 'government-ethics-accountability', 'icon' => '🏛️', 'sort_order' => 40],
        ];

        foreach ($topics as $topic) {
            PoliticianTopic::updateOrCreate(
                ['slug' => $topic['slug']],
                $topic
            );
        }
    }
}
