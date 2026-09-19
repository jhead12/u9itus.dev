<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * A visitor-submitted "this looks wrong" note about a candidate or officeholder
 * card. Reviewed on the admin Data Reports page; never applied automatically.
 */
class DataReport extends Model
{
    public const STATUS_PENDING = 'pending';

    public const STATUS_RESOLVED = 'resolved';

    public const STATUS_DISMISSED = 'dismissed';

    public const SUBJECT_TYPES = ['politician', 'election_candidate_record', 'ballot_measure', 'other'];

    /** problem key => label shown in the form and the admin list */
    public const PROBLEMS = [
        'wrong_name' => 'Name is wrong or misspelled',
        'not_a_person' => 'Not a real candidate',
        'duplicate' => 'Listed more than once',
        'wrong_party' => 'Wrong party',
        'wrong_office' => 'Wrong office or district',
        'wrong_dates' => 'Wrong election or term dates',
        'outdated' => 'Out of date (no longer running / in office)',
        'other' => 'Something else',
    ];

    protected $fillable = [
        'subject_type', 'subject_id', 'subject_name', 'subject_office', 'state', 'problem', 'message',
        'page_url', 'source_label', 'reporter_hash', 'status',
        'resolved_by_user_id', 'resolved_at', 'resolution_note',
    ];

    protected function casts(): array
    {
        return ['resolved_at' => 'datetime'];
    }

    public function resolvedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'resolved_by_user_id');
    }
}
