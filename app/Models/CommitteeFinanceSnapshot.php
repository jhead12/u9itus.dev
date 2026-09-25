<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Collection;

/**
 * A committee's totals from one campaign finance filing. See the
 * committee_finance_snapshots migration.
 */
class CommitteeFinanceSnapshot extends Model
{
    protected $fillable = [
        'state',
        'committee_id',
        'source',
        'filing_id',
        'amend_id',
        'form_type',
        'period_start',
        'period_end',
        'filed_on',
        'contributions_period',
        'contributions_ytd',
        'nonmonetary_ytd',
        'expenditures_ytd',
        'cash_on_hand',
        'declared_measure_number',
        'declared_measure_name',
        'declared_position',
        'declared_measures',
    ];

    protected function casts(): array
    {
        return [
            'period_start' => 'date',
            'period_end' => 'date',
            'filed_on' => 'date',
            'contributions_period' => 'float',
            'contributions_ytd' => 'float',
            'nonmonetary_ytd' => 'float',
            'expenditures_ytd' => 'float',
            'cash_on_hand' => 'float',
            'declared_measures' => 'array',
        ];
    }

    /**
     * Every measure this filing declares, as number / position / election_date /
     * description entries. Falls back to the single declared_* columns.
     *
     * @return list<array{number: ?string, position: ?string, election_date: ?string, description: ?string}>
     */
    public function declaredMeasures(): array
    {
        if (! empty($this->declared_measures)) {
            return array_values($this->declared_measures);
        }
        if ($this->declared_measure_number === null && $this->declared_position === null) {
            return [];
        }

        return [[
            'number' => $this->declared_measure_number,
            'position' => $this->declared_position,
            'election_date' => null,
            'description' => $this->declared_measure_name,
        ]];
    }

    public static function keyFor(string $state, string $committeeId): string
    {
        return strtoupper($state).'|'.$committeeId;
    }

    /** The committee's most recent filing, or null if none has been imported. */
    public static function latestFor(string $state, string $committeeId): ?self
    {
        return self::query()
            ->where('state', strtoupper($state))
            ->where('committee_id', $committeeId)
            ->orderByDesc('period_end')->orderByDesc('filed_on')->orderByDesc('id')
            ->first();
    }

    /**
     * Latest filing for each committee in $links, keyed by keyFor() — optionally the latest
     * within one calendar year (a decided measure's election year).
     *
     * @param  iterable<BallotMeasureCommittee>  $links
     * @return Collection<string, self>
     */
    public static function latestForLinks(iterable $links, ?int $year = null): Collection
    {
        $links = collect($links);
        if ($links->isEmpty()) {
            return collect();
        }

        return self::query()
            ->whereIn('committee_id', $links->pluck('committee_id')->unique()->values())
            ->whereIn('state', $links->pluck('state')->unique()->values())
            ->when($year !== null, fn ($q) => $q->whereBetween('period_end', ["{$year}-01-01", "{$year}-12-31"]))
            ->orderByDesc('period_end')->orderByDesc('filed_on')->orderByDesc('id')
            ->get()
            ->unique(fn (self $s) => self::keyFor($s->state, $s->committee_id))
            ->keyBy(fn (self $s) => self::keyFor($s->state, $s->committee_id));
    }
}
