<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::table('politicians', function (Blueprint $table) {
            // Optional override email for receipt delivery (e.g., when using a third-party card)
            $table->string('receipt_email')->nullable()->after('stripe_customer_id')
                ->comment('Override email for receipt delivery; if null, uses account email');
        });
    }

    public function down(): void
    {
        Schema::table('politicians', function (Blueprint $table) {
            $table->dropColumn('receipt_email');
        });
    }
};
