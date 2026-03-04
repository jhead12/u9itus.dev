<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('notification_preferences', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            
            // Email preferences
            $table->boolean('email_campaign_status')->default(true);
            $table->boolean('email_payout_processed')->default(true);
            $table->boolean('email_low_balance')->default(true);
            $table->boolean('email_fraud_alert')->default(true);
            $table->boolean('email_system_announcements')->default(true);
            
            // In-app preferences
            $table->boolean('inapp_campaign_status')->default(true);
            $table->boolean('inapp_payout_processed')->default(true);
            $table->boolean('inapp_low_balance')->default(true);
            $table->boolean('inapp_fraud_alert')->default(true);
            $table->boolean('inapp_system_announcements')->default(true);
            
            // Push notification preferences
            $table->boolean('push_campaign_status')->default(false);
            $table->boolean('push_payout_processed')->default(false);
            $table->boolean('push_low_balance')->default(false);
            $table->boolean('push_fraud_alert')->default(true);
            $table->boolean('push_system_announcements')->default(false);
            
            // SMS preferences
            $table->boolean('sms_campaign_status')->default(false);
            $table->boolean('sms_payout_processed')->default(false);
            $table->boolean('sms_low_balance')->default(false);
            $table->boolean('sms_fraud_alert')->default(false);
            $table->boolean('sms_system_announcements')->default(false);
            
            // FCM token for push notifications
            $table->string('fcm_token')->nullable();
            
            // Phone number for SMS
            $table->string('phone_number')->nullable();
            
            $table->timestamps();
            
            $table->unique('user_id');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('notification_preferences');
    }
};
