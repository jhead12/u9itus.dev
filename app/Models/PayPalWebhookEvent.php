<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class PayPalWebhookEvent extends Model
{
    protected $table = 'paypal_webhook_events';

    protected $fillable = [
        'paypal_event_id',
        'event_type',
        'resource_reference',
        'payload',
        'processed_at',
    ];

    protected function casts(): array
    {
        return [
            'payload' => 'array',
            'processed_at' => 'datetime',
        ];
    }
}
