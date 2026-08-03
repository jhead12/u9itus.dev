<?php

namespace App\Http\Controllers;

use App\Models\NotificationPreference;
use Illuminate\Http\Request;

class NotificationPreferenceController extends Controller
{
    /**
     * Show the notification preferences form.
     */
    public function edit(Request $request)
    {
        $preferences = $request->user()->notificationPreference
            ?? NotificationPreference::create(['user_id' => $request->user()->id]);

        return view('standalone.shared.notification-preferences', compact('preferences'));
    }

    /**
     * Update the notification preferences.
     */
    public function update(Request $request)
    {
        $validated = $request->validate([
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
            'phone_number' => 'nullable|string|max:20',
        ]);

        // Ensure all boolean fields have a value (unchecked boxes don't submit)
        foreach(['email', 'inapp', 'push', 'sms'] as $channel) {
            foreach(['campaign_status', 'payout_processed', 'low_balance', 'fraud_alert', 'system_announcements'] as $type) {
                $key = "{$channel}_{$type}";
                if (!isset($validated[$key])) {
                    $validated[$key] = false;
                }
            }
        }

        // email_boundary_digest is email-only (no inapp/push/sms equivalent
        // exists yet), so it isn't covered by the channel loop above.
        if (!isset($validated['email_boundary_digest'])) {
            $validated['email_boundary_digest'] = false;
        }

        $preferences = $request->user()->notificationPreference
            ?? NotificationPreference::create(['user_id' => $request->user()->id]);

        $preferences->update($validated);

        return redirect()->back()->with('success', 'Notification preferences updated successfully.');
    }

    /**
     * Store FCM token for push notifications.
     */
    public function storeFcmToken(Request $request)
    {
        $validated = $request->validate([
            'fcm_token' => 'required|string',
        ]);

        $preferences = $request->user()->notificationPreference
            ?? NotificationPreference::create(['user_id' => $request->user()->id]);

        $preferences->update(['fcm_token' => $validated['fcm_token']]);

        return response()->json(['message' => 'FCM token registered successfully.']);
    }
}
