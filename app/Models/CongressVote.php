<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class CongressVote extends Model
{
    protected $fillable = [
        'chamber', 'congress', 'session', 'roll_number', 'voted_at', 'question', 'title',
        'bill_number', 'result', 'yeas', 'nays', 'present', 'not_voting', 'party_positions', 'source_url',
    ];

    protected $casts = [
        'voted_at' => 'datetime',
        'party_positions' => 'array',
    ];

    public function memberVotes(): HasMany
    {
        return $this->hasMany(CongressMemberVote::class);
    }

    /** Topics an editor tied this roll call to, with what a yea vote means for each. */
    public function topicTags(): HasMany
    {
        return $this->hasMany(CongressVoteTopic::class);
    }

    /** Congress.gov page for the bill or nomination this roll call was about, when the number is recognisable. */
    public function billUrl(): ?string
    {
        $number = strtoupper((string) preg_replace('/[\s.]+/', '', (string) $this->bill_number));
        if ($number === '') {
            return null;
        }

        $congress = $this->congress.$this->ordinalSuffix($this->congress).'-congress';

        if (preg_match('/^PN(\d+)/', $number, $m)) {
            return "https://www.congress.gov/nomination/{$congress}/{$m[1]}";
        }

        $types = [
            'HR' => 'house-bill', 'S' => 'senate-bill',
            'HRES' => 'house-resolution', 'SRES' => 'senate-resolution',
            'HJRES' => 'house-joint-resolution', 'SJRES' => 'senate-joint-resolution',
            'HCONRES' => 'house-concurrent-resolution', 'SCONRES' => 'senate-concurrent-resolution',
        ];

        if (preg_match('/^([A-Z]+?)(\d+)$/', $number, $m) && isset($types[$m[1]])) {
            return "https://www.congress.gov/bill/{$congress}/{$types[$m[1]]}/{$m[2]}";
        }

        return null;
    }

    private function ordinalSuffix(int $n): string
    {
        if (in_array($n % 100, [11, 12, 13], true)) {
            return 'th';
        }

        return [1 => 'st', 2 => 'nd', 3 => 'rd'][$n % 10] ?? 'th';
    }
}
