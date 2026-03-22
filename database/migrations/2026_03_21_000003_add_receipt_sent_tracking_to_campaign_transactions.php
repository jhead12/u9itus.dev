<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::table('campaign_transactions', function (Blueprint $table) {
            // Track when receipt was sent for audit and resend purposes
            $table->timestamp('receipt_sent_at')->nullable()->after('status')
                ->comment('When the receipt email was successfully sent');
        });
    }

    public function down(): void
    {
        Schema::table('campaign_transactions', function (Blueprint $table) {
            $table->dropColumn('receipt_sent_at');
        });
    }
};
