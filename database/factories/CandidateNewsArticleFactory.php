<?php

namespace Database\Factories;

use App\Models\CandidateNewsArticle;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<CandidateNewsArticle>
 */
class CandidateNewsArticleFactory extends Factory
{
    protected $model = CandidateNewsArticle::class;

    public function definition(): array
    {
        $headline = $this->faker->sentence(8);

        return [
            'politician_id'        => null,
            'candidate_name'       => $this->faker->name(),
            'headline'             => $headline,
            'source_name'          => $this->faker->company(),
            'source_url'           => $this->faker->url(),
            'snippet'              => $this->faker->paragraph(3),
            'image_url'            => $this->faker->optional(0.7)->imageUrl(),
            'published_at'         => $this->faker->dateTimeBetween('-3 days', 'now'),
            'provider'             => 'google_rss',
            'source_hash'          => hash('sha256', Str::uuid()->toString()),
            'verification_status'  => 'verified',
            'content_type'         => 'news',
            'scraped_at'           => now(),
        ];
    }
}