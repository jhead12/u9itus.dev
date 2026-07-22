<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class EventReminder extends Model
{
    use HasFactory;

    protected $table = 'event_reminders';

    protected $fillable = [
        'civic_event_id',
        'user_id',
        'hours_before',
    ];

    protected function casts(): array
    {
        return [
            'hours_before' => 'integer',
        ];
    }

    public function event(): \Illuminate\Database\Eloquent\Relations\BelongsTo
    {
        return $this->belongsTo(CivicEvent::class, 'civic_event_id');
    }

    public function user(): \Illuminate\Database\Eloquent\Relations\BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
