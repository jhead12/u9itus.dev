<?php

namespace App\Support\Workspace;

/**
 * Registry of widgets a citizen can place on "My Workspace". Mirrors
 * WidgetCatalog (admin), but kept as a separate class/key-space — the
 * two catalogs share the workspace_widgets table (keyed by user_id), so
 * every key here is prefixed 'citizen_' to guarantee it can never collide
 * with an admin widget_key for a dual-role user.
 */
final class CitizenWidgetCatalog
{
    /**
     * @return array<string, array{label: string, description: string, default_width: string}>
     */
    public static function all(): array
    {
        return [
            'citizen_activity_snapshot' => [
                'label' => 'My Activity',
                'description' => 'Campaigns, blog posts, events, and credit balance at a glance.',
                'default_width' => 'full',
            ],
            'citizen_topics_badges' => [
                'label' => 'My Interests',
                'description' => 'Topics you follow — drives what shows up in your local news widget.',
                'default_width' => 'half',
            ],
            'citizen_local_news' => [
                'label' => 'Local News For You',
                'description' => 'Verified local news matched to your interests and area.',
                'default_width' => 'half',
            ],
            'citizen_voting_updates' => [
                'label' => 'Voting Updates',
                'description' => 'Upcoming election dates and new ballot measures in your state.',
                'default_width' => 'half',
            ],
            'citizen_campaigns_overview' => [
                'label' => 'My Campaigns',
                'description' => 'Status and progress of the campaigns you have running.',
                'default_width' => 'half',
            ],
        ];
    }

    public static function keys(): array
    {
        return array_keys(self::all());
    }

    public static function has(string $key): bool
    {
        return isset(self::all()[$key]);
    }

    public static function defaultWidthFor(string $key): string
    {
        return self::all()[$key]['default_width'] ?? 'half';
    }

    /** Seed layout for a first-time workspace visit. */
    public static function defaultKeys(): array
    {
        return ['citizen_activity_snapshot', 'citizen_topics_badges', 'citizen_local_news', 'citizen_voting_updates'];
    }
}
