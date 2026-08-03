<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class NotificationPreference extends Model
{
    use HasFactory;

    /**
     * The attributes that are mass assignable.
     *
     * @var array<int, string>
     */
    protected $fillable = [
        'user_id',
        // Email preferences
        'email_campaign_status',
        'email_payout_processed',
        'email_low_balance',
        'email_fraud_alert',
        'email_system_announcements',
        'email_boundary_digest',
        // In-app preferences
        'inapp_campaign_status',
        'inapp_payout_processed',
        'inapp_low_balance',
        'inapp_fraud_alert',
        'inapp_system_announcements',
        // Push notification preferences
        'push_campaign_status',
        'push_payout_processed',
        'push_low_balance',
        'push_fraud_alert',
        'push_system_announcements',
        // SMS preferences
        'sms_campaign_status',
        'sms_payout_processed',
        'sms_low_balance',
        'sms_fraud_alert',
        'sms_system_announcements',
        // Tokens
        'fcm_token',
        'phone_number',
    ];

    /**
     * The attributes that should be cast.
*/
    protected $casts = [
        'email_campaign_status' => 'boolean',
        'email_payout_processed' => 'boolean',
        'email_low_balance' => 'boolean',
        'email_fraud_alert' => 'boolean',
        'email_system_announcements' => 'boolean',
        'email_boundary_digest' => 'boolean',
        'inapp_campaign_status' => 'boolean',
        'inapp_payout_processed' => 'boolean',
        'inapp_low_balance' => 'boolean',
        'inapp_fraud_alert' => 'boolean',
        'inapp_system_announcements' => 'boolean',
        'push_campaign_status' => 'boolean',
        'push_payout_processed' => 'boolean',
        'push_low_balance' => 'boolean',
        'push_fraud_alert' => 'boolean',
        'push_system_announcements' => 'boolean',
        'sms_campaign_status' => 'boolean',
        'sms_payout_processed' => 'boolean',
        'sms_low_balance' => 'boolean',
        'sms_fraud_alert' => 'boolean',
        'sms_system_announcements' => 'boolean',
    ];

    /**
     * Get the user that owns the notification preferences.
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * Check if a channel is enabled for a notification type.
     */
    public function isEnabled(string $channel, string $notificationType): bool
    {
        $field = "{$channel}_{$notificationType}";
        
        return $this->$field ?? false;
    }

    /**
     * Get all enabled channels for a notification type.
     */
    public function getEnabledChannels(string $notificationType): array
    {
        $channels = [];
        
        foreach (['email', 'inapp', 'push', 'sms'] as $channel) {
            if ($this->isEnabled($channel, $notificationType)) {
                $channels[] = $channel;
            }
        }
        
        return $channels;
    }
}
