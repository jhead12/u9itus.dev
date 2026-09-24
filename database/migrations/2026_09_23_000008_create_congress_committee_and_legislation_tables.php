<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Committee seats and legislative focus for members of Congress, keyed by Bioguide ID like
 * the roll-call tables, plus the matching vocabulary that turns news, bill titles and
 * Congress.gov policy areas into politician_topics.
 */
return new class extends Migration
{
    // Synonyms that should tag text with the topic even when its name never appears.
    // Multi-word phrases are preferred: a single generic word would tag too much.
    private const KEYWORDS = [
        'healthcare' => ['health care', 'health insurance', 'affordable care act', 'obamacare', 'hospital', 'medicaid', 'prescription drug', 'drug prices', 'public health'],
        'education' => ['school', 'teacher', 'student', 'k-12', 'college', 'university', 'curriculum', 'charter school', 'school choice'],
        'climate-action' => ['climate change', 'carbon emissions', 'greenhouse gas', 'net zero', 'clean energy', 'global warming', 'paris agreement'],
        'jobs-economy' => ['inflation', 'economy', 'economic growth', 'unemployment', 'job creation', 'jobs report', 'cost of living', 'recession'],
        'housing' => ['housing', 'rent', 'renters', 'mortgage', 'homeless', 'homelessness', 'eviction', 'zoning'],
        'public-safety' => ['police', 'policing', 'law enforcement', 'crime', 'violent crime', 'first responders', 'fentanyl trafficking'],
        'transportation' => ['highway', 'transit', 'rail', 'amtrak', 'airport', 'aviation', 'faa', 'traffic'],
        'infrastructure' => ['infrastructure', 'bridges', 'broadband', 'water system', 'power grid', 'roads and bridges'],
        'tax-policy' => ['tax cut', 'tax cuts', 'taxes', 'irs', 'tax credit', 'income tax', 'property tax', 'tax code', 'tariff revenue'],
        'voting-rights' => ['voter id', 'ballot access', 'voter suppression', 'mail-in ballot', 'voter registration', 'election integrity', 'gerrymander', 'redistricting'],
        'small-business' => ['small business', 'small businesses', 'entrepreneur', 'sba', 'main street'],
        'immigration' => ['border', 'migrant', 'migrants', 'asylum', 'deportation', 'ice raids', 'daca', 'dreamers', 'undocumented', 'visa'],
        'environment' => ['epa', 'pollution', 'clean water', 'clean air', 'conservation', 'wildlife', 'public lands', 'national park', 'endangered species'],
        'cybersecurity' => ['cyberattack', 'cyber attack', 'hackers', 'ransomware', 'data breach', 'cyber security'],
        'agriculture' => ['farm bill', 'farmers', 'farm', 'crop', 'usda', 'ranchers', 'snap benefits', 'food stamps'],
        'healthcare-access' => ['rural hospital', 'uninsured', 'medicaid expansion', 'access to care', 'community health center'],
        'affordable-housing' => ['affordable housing', 'housing affordability', 'low-income housing', 'section 8', 'housing voucher', 'first-time homebuyer'],
        'gun-control' => ['gun', 'guns', 'firearm', 'firearms', 'second amendment', '2nd amendment', 'assault weapon', 'background check', 'red flag law', 'gun violence', 'mass shooting', 'nra', 'ghost gun'],
        'democracy' => ['january 6', 'jan. 6', 'insurrection', 'rule of law', 'authoritarian', 'filibuster', 'supreme court ethics'],
        'student-debt' => ['student loan', 'student loans', 'loan forgiveness', 'pell grant', 'college affordability'],
        'foreign-policy' => ['ukraine', 'russia', 'israel', 'gaza', 'china', 'nato', 'iran', 'taiwan', 'foreign aid', 'diplomacy', 'sanctions'],
        'national-security' => ['pentagon', 'military', 'defense spending', 'armed forces', 'troops', 'ndaa', 'national defense', 'terrorism'],
        'criminal-justice-reform' => ['sentencing reform', 'bail reform', 'incarceration', 'prison reform', 'mass incarceration', 'death penalty', 'police reform', 'expungement'],
        'veterans-affairs' => ['veterans', 'va hospital', 'department of veterans affairs', 'gi bill', 'burn pits', 'pact act'],
        'social-security' => ['social security', 'retirees', 'retirement benefits', 'ssi', 'disability insurance'],
        'medicare' => ['medicare', 'medicare advantage', 'seniors health'],
        'minimum-wage' => ['minimum wage', 'living wage', '$15 an hour', 'wage increase'],
        'labor-rights' => ['unions', 'labor union', 'collective bargaining', 'workers rights', 'nlrb', 'right to work'],
        'lgbtq-rights' => ['lgbtq', 'lgbt', 'transgender', 'same-sex marriage', 'gay rights', 'gender-affirming'],
        'reproductive-rights' => ['abortion', 'roe v. wade', 'dobbs', 'planned parenthood', 'contraception', 'ivf', 'pro-life', 'pro-choice'],
        'racial-justice' => ['civil rights', 'racial equity', 'racism', 'discrimination', 'reparations', 'hate crime'],
        'disability-rights' => ['disability', 'disabilities', 'disabled', 'americans with disabilities act', 'accessibility'],
        'mental-health' => ['mental health', 'suicide prevention', 'behavioral health', 'psychiatric'],
        'opioid-addiction-crisis' => ['opioid', 'opioids', 'fentanyl', 'overdose', 'addiction', 'substance use', 'naloxone'],
        'energy-policy' => ['oil and gas', 'drilling', 'pipeline', 'natural gas', 'nuclear power', 'energy prices', 'gas prices', 'electric grid', 'solar', 'wind power'],
        'trade-policy' => ['tariff', 'tariffs', 'trade deal', 'trade agreement', 'trade war', 'usmca', 'imports', 'exports'],
        'technology-ai-policy' => ['artificial intelligence', 'ai regulation', 'big tech', 'social media', 'tiktok', 'section 230', 'semiconductor', 'data center', 'data centers'],
        'privacy-rights' => ['data privacy', 'privacy', 'surveillance', 'fisa', 'facial recognition', 'personal data'],
        'campaign-finance-reform' => ['citizens united', 'dark money', 'super pac', 'campaign finance', 'campaign contributions', 'stock trading ban'],
        'government-ethics-accountability' => ['ethics', 'corruption', 'inspector general', 'oversight', 'conflict of interest', 'bribery', 'indictment'],
    ];

    // Congress.gov assigns every bill exactly one of 32 policy areas. Broad areas
    // (Government Operations, Civil Rights, Social Welfare, Law, Congress...) are left
    // unmapped on purpose: those bills are tagged by their title keywords instead.
    private const POLICY_AREAS = [
        'agriculture' => ['Agriculture and Food'],
        'national-security' => ['Armed Forces and National Security'],
        'jobs-economy' => ['Commerce', 'Economics and Public Finance', 'Finance and Financial Sector'],
        'public-safety' => ['Crime and Law Enforcement', 'Emergency Management'],
        'education' => ['Education'],
        'energy-policy' => ['Energy'],
        'environment' => ['Environmental Protection', 'Public Lands and Natural Resources'],
        'trade-policy' => ['Foreign Trade and International Finance'],
        'healthcare' => ['Health'],
        'housing' => ['Housing and Community Development'],
        'immigration' => ['Immigration'],
        'foreign-policy' => ['International Affairs'],
        'labor-rights' => ['Labor and Employment'],
        'technology-ai-policy' => ['Science, Technology, Communications'],
        'tax-policy' => ['Taxation'],
        'transportation' => ['Transportation and Public Works'],
        'infrastructure' => ['Water Resources Development'],
    ];

    public function up(): void
    {
        if (! Schema::hasTable('congress_committee_assignments')) {
            Schema::create('congress_committee_assignments', function (Blueprint $table) {
                $table->id();
                $table->string('bioguide_id', 12);
                // Thomas ID; subcommittees append their own code to the parent's (HSAG15).
                $table->string('committee_code', 12);
                $table->string('parent_code', 12)->nullable();
                $table->string('name', 255);
                // house | senate | joint
                $table->string('chamber', 10);
                // Chairman, Ranking Member, Vice Chair... null for rank-and-file members.
                $table->string('title', 60)->nullable();
                $table->unsignedSmallInteger('rank')->nullable();
                // majority | minority
                $table->string('side', 10)->nullable();
                $table->string('url', 500)->nullable();
                $table->timestamps();

                $table->unique(['bioguide_id', 'committee_code'], 'congress_committee_assignments_unique');
            });
        }

        if (! Schema::hasTable('congress_member_legislation')) {
            Schema::create('congress_member_legislation', function (Blueprint $table) {
                $table->id();
                $table->string('bioguide_id', 12)->unique();
                // Oldest Congress included in the counts below.
                $table->unsignedSmallInteger('since_congress');
                $table->unsignedInteger('sponsored_total')->default(0);
                $table->unsignedInteger('cosponsored_total')->default(0);
                // {"Health": {"sponsored": 4, "cosponsored": 31}, ...}
                $table->json('policy_areas')->nullable();
                // Same shape keyed by politician_topics slug (policy area + title keywords).
                $table->json('topics')->nullable();
                $table->timestamps();
            });
        }

        if (! Schema::hasColumn('politician_topics', 'keywords')) {
            Schema::table('politician_topics', function (Blueprint $table) {
                $table->json('keywords')->nullable();
                $table->json('policy_areas')->nullable();
            });
        }

        if (! Schema::hasColumn('politician_topic_signals', 'legislation_count')) {
            Schema::table('politician_topic_signals', function (Blueprint $table) {
                $table->unsignedInteger('legislation_count')->default(0);
            });
        }

        foreach (self::KEYWORDS as $slug => $keywords) {
            DB::table('politician_topics')->where('slug', $slug)->update([
                'keywords' => json_encode($keywords),
                'policy_areas' => json_encode(self::POLICY_AREAS[$slug] ?? []),
            ]);
        }
    }

    public function down(): void
    {
        if (Schema::hasColumn('politician_topic_signals', 'legislation_count')) {
            Schema::table('politician_topic_signals', function (Blueprint $table) {
                $table->dropColumn('legislation_count');
            });
        }

        if (Schema::hasColumn('politician_topics', 'keywords')) {
            Schema::table('politician_topics', function (Blueprint $table) {
                $table->dropColumn(['keywords', 'policy_areas']);
            });
        }

        Schema::dropIfExists('congress_member_legislation');
        Schema::dropIfExists('congress_committee_assignments');
    }
};
