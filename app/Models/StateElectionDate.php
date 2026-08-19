<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class StateElectionDate extends Model
{
    protected $fillable = [
        'state',
        'election_year',
        'stage_name',
        'election_date',
        'filing_deadline',
        'votesmart_election_id',
        'civic_election_id',
        'source',
    ];

    protected function casts(): array
    {
        return [
            'election_date' => 'date',
            'filing_deadline' => 'date',
        ];
    }

    /**
     * Upcoming election stages (primary, general, etc.) for a state, in a
     * shape ready for direct use by the map API, public profile, and voter
     * dashboard — the single shared query so those consumers don't each
     * duplicate it.
     *
     * @return array<int, array{stage_name: string, election_date: ?string, election_date_formatted: ?string, filing_deadline: ?string, filing_deadline_formatted: ?string}>
     */
    public static function upcomingForState(string $state): array
    {
        return static::query()
            ->where('state', strtoupper($state))
            ->where(function ($q) {
                $q->whereNull('election_date')->orWhere('election_date', '>=', now()->toDateString());
            })
            ->orderBy('election_date')
            ->get()
            ->map(fn (self $row) => [
                'stage_name' => $row->stage_name,
                'election_date' => optional($row->election_date)?->toDateString(),
                'election_date_formatted' => optional($row->election_date)?->format('M j, Y'),
                'filing_deadline' => optional($row->filing_deadline)?->toDateString(),
                'filing_deadline_formatted' => optional($row->filing_deadline)?->format('M j, Y'),
            ])
            ->values()
            ->all();
    }
}
