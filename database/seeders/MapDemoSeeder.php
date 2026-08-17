<?php

namespace Database\Seeders;

use App\Enums\CivicEventStatus;
use App\Enums\CivicEventType;
use App\Enums\PostStatus;
use App\Models\Citizen;
use App\Models\CivicEvent;
use App\Models\Post;
use Illuminate\Database\Seeder;

/**
 * Demo data for the /map "Local Businesses" and "Civic Content" layers —
 * not run as part of the default DatabaseSeeder chain since it's
 * throwaway testing data, not baseline app data. Run explicitly:
 *   php artisan db:seed --class=MapDemoSeeder
 *
 * Coordinates are real city centers (not geocoded) so pins land somewhere
 * recognizable immediately, without depending on the Census API at seed time.
 */
class MapDemoSeeder extends Seeder
{
    public function run(): void
    {
        $businesses = [
            ['name' => 'Golden Gate Bakery', 'category' => 'food', 'city' => 'San Francisco', 'state' => 'CA', 'zip' => '94102', 'addr' => '1 Market St', 'lat' => 37.7749, 'lng' => -122.4194],
            ['name' => 'Austin BBQ Pit', 'category' => 'food', 'city' => 'Austin', 'state' => 'TX', 'zip' => '78701', 'addr' => '200 Congress Ave', 'lat' => 30.2672, 'lng' => -97.7431],
            ['name' => 'Brooklyn Vintage Boutique', 'category' => 'retail', 'city' => 'Brooklyn', 'state' => 'NY', 'zip' => '11201', 'addr' => '45 Court St', 'lat' => 40.6782, 'lng' => -73.9442],
            ['name' => 'Chicago Hardware Co', 'category' => 'retail', 'city' => 'Chicago', 'state' => 'IL', 'zip' => '60601', 'addr' => '78 State St', 'lat' => 41.8781, 'lng' => -87.6298],
            ['name' => 'Denver Auto Repair', 'category' => 'service', 'city' => 'Denver', 'state' => 'CO', 'zip' => '80202', 'addr' => '900 16th St', 'lat' => 39.7392, 'lng' => -104.9903],
            ['name' => 'Miami Legal Clinic', 'category' => 'service', 'city' => 'Miami', 'state' => 'FL', 'zip' => '33130', 'addr' => '300 Brickell Ave', 'lat' => 25.7617, 'lng' => -80.1918],
            ['name' => 'Seattle Community Food Bank', 'category' => 'nonprofit', 'city' => 'Seattle', 'state' => 'WA', 'zip' => '98101', 'addr' => '500 Pike St', 'lat' => 47.6062, 'lng' => -122.3321],
            ['name' => 'Phoenix Community Center', 'category' => 'other', 'city' => 'Phoenix', 'state' => 'AZ', 'zip' => '85004', 'addr' => '100 Central Ave', 'lat' => 33.4484, 'lng' => -112.0740],
        ];

        $citizens = [];
        foreach ($businesses as $b) {
            $citizens[$b['name']] = Citizen::updateOrCreate(
                ['business_name' => $b['name']],
                [
                    'full_name' => $b['name'].' Owner',
                    'business_category' => $b['category'],
                    'city' => $b['city'],
                    'state' => $b['state'],
                    'zip' => $b['zip'],
                    'address_line_1' => $b['addr'],
                    'latitude' => $b['lat'],
                    'longitude' => $b['lng'],
                    'show_on_map' => true,
                    'is_active' => true,
                    'verified_at' => now(),
                    'bio' => "Demo business seeded for map testing — {$b['category']}.",
                ]
            );
        }

        $this->command?->info('Seeded '.count($citizens).' demo businesses onto the map.');

        $posts = [
            [
                'author' => 'Golden Gate Bakery',
                'title' => 'How Our Bakery Is Getting Involved in Local Elections',
                'excerpt' => 'Why we started hosting candidate meet-and-greets on Saturday mornings.',
                'location_name' => 'San Francisco, CA',
                'lat' => 37.7749, 'lng' => -122.4194, 'city' => 'San Francisco', 'state' => 'CA',
            ],
            [
                'author' => 'Chicago Hardware Co',
                'title' => 'Supporting Small Business Owners This Election Season',
                'excerpt' => 'A rundown of the ballot measures that matter most to Main Street.',
                'location_name' => 'Chicago, IL',
                'lat' => 41.8781, 'lng' => -87.6298, 'city' => 'Chicago', 'state' => 'IL',
            ],
            [
                'author' => 'Seattle Community Food Bank',
                'title' => 'Why Civic Engagement Matters for Nonprofits',
                'excerpt' => 'The policies shaping food access in our neighborhood, explained.',
                'location_name' => 'Seattle, WA',
                'lat' => 47.6062, 'lng' => -122.3321, 'city' => 'Seattle', 'state' => 'WA',
            ],
        ];

        foreach ($posts as $p) {
            $citizen = $citizens[$p['author']];
            Post::updateOrCreate(
                ['title' => $p['title']],
                [
                    'author_type' => Citizen::class,
                    'author_id' => $citizen->id,
                    'excerpt' => $p['excerpt'],
                    'body' => '<p>'.$p['excerpt'].' This is demo content seeded for testing the map\'s Civic Content layer.</p>',
                    'status' => PostStatus::Published,
                    'published_at' => now()->subDays(random_int(1, 10)),
                    'location_name' => $p['location_name'],
                    'latitude' => $p['lat'],
                    'longitude' => $p['lng'],
                    'city' => $p['city'],
                    'state' => $p['state'],
                ]
            );
        }

        $this->command?->info('Seeded '.count($posts).' demo geo-tagged posts.');

        $events = [
            [
                'host' => 'Austin BBQ Pit',
                'title' => 'Meet Your City Council Candidates',
                'type' => CivicEventType::TownHall,
                'venue' => 'Austin BBQ Pit Patio',
                'city' => 'Austin', 'state' => 'TX',
                'lat' => 30.2672, 'lng' => -97.7431,
                'days' => 7,
            ],
            [
                'host' => 'Denver Auto Repair',
                'title' => 'Neighborhood Town Hall',
                'type' => CivicEventType::CommunityMeeting,
                'venue' => 'Denver Auto Repair Lot',
                'city' => 'Denver', 'state' => 'CO',
                'lat' => 39.7392, 'lng' => -104.9903,
                'days' => 14,
            ],
            [
                'host' => 'Phoenix Community Center',
                'title' => 'Voter Registration Drive',
                'type' => CivicEventType::Workshop,
                'venue' => 'Phoenix Community Center',
                'city' => 'Phoenix', 'state' => 'AZ',
                'lat' => 33.4484, 'lng' => -112.0740,
                'days' => 21,
            ],
        ];

        foreach ($events as $e) {
            $citizen = $citizens[$e['host']];
            $start = now()->addDays($e['days'])->setTime(18, 0);

            CivicEvent::updateOrCreate(
                ['title' => $e['title']],
                [
                    'host_type' => Citizen::class,
                    'host_id' => $citizen->id,
                    'event_type' => $e['type'],
                    'status' => CivicEventStatus::Published,
                    'description' => "Demo event seeded for testing the map's Civic Content layer.",
                    'location_name' => $e['city'].', '.$e['state'],
                    'venue_name' => $e['venue'],
                    'city' => $e['city'],
                    'state' => $e['state'],
                    'latitude' => $e['lat'],
                    'longitude' => $e['lng'],
                    'starts_at' => $start,
                    'ends_at' => (clone $start)->addHours(2),
                    'timezone' => 'America/New_York',
                    'is_virtual' => false,
                    'rsvp_requires_approval' => false,
                ]
            );
        }

        $this->command?->info('Seeded '.count($events).' demo geo-tagged civic events.');
    }
}
