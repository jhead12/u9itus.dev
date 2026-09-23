<?php

namespace App\Support\Workspace;

/**
 * Registry of widgets a workspace can place. A widget's key is what's stored
 * in workspace_widgets.widget_key and is the only thing a request can
 * reference — WorkspaceController rejects any key not listed here, so this
 * is also the full set of data a workspace can ever expose.
 */
final class WidgetCatalog
{
    /**
     * @return array<string, array{label: string, description: string, default_width: string}>
     */
    public static function all(): array
    {
        return [
            'stats_snapshot' => [
                'label' => 'Platform Snapshot',
                'description' => 'Users, campaigns, and moderation queue counts at a glance.',
                'default_width' => 'full',
            ],
            'pending_queues' => [
                'label' => 'Pending Queues',
                'description' => 'Campaign approvals, candidate matches, data quality, and KYC awaiting review.',
                'default_width' => 'half',
            ],
            'workflow_health' => [
                'label' => 'Cleanup Workflow Health',
                'description' => 'Latest run status for the politician data-cleanup pipeline.',
                'default_width' => 'half',
            ],
            'local_news_feed' => [
                'label' => 'Local Election News',
                'description' => 'Recently verified district news covering polling, ballots, and election administration.',
                'default_width' => 'half',
            ],
            'voting_updates' => [
                'label' => 'Voting Updates',
                'description' => 'Upcoming state election dates and newly added ballot measures.',
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
        return ['stats_snapshot', 'pending_queues', 'workflow_health', 'local_news_feed'];
    }
}
