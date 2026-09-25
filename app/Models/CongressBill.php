<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * A bill referenced by a floor speech, with its Congress.gov title and policy area and
 * the topic they map to. Fetched once by BillTopicResolver.
 */
class CongressBill extends Model
{
    protected $fillable = ['bill_key', 'title', 'policy_area', 'topic_key', 'fetched_at'];

    protected function casts(): array
    {
        return ['fetched_at' => 'datetime'];
    }

    /**
     * "119-hr-3633" → [119, 'hr', 3633], or null when the key is malformed.
     *
     * @return array{0: int, 1: string, 2: int}|null
     */
    public static function parseKey(string $key): ?array
    {
        return preg_match('/^(\d+)-([a-z]+)-(\d+)$/', $key, $m) ? [(int) $m[1], $m[2], (int) $m[3]] : null;
    }
}
