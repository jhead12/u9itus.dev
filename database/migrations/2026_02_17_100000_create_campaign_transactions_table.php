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
        Schema::create('campaign_transactions', function (Blueprint $table) {
            $table->bigIncrements('id');
            $table->uuid('uuid')->nullable()->unique();
            $table->foreignId('campaign_id')->nullable()->constrained('political_campaigns')->nullOnDelete();
            $table->foreignId('politician_id')->nullable()->constrained('politicians')->nullOnDelete();

            $table->string('transaction_type'); // charge, refund, adjustment
            $table->decimal('amount', 12, 2);
            $table->string('currency', 3)->default('USD');

            $table->string('stripe_payment_intent_id')->nullable();
            $table->string('stripe_charge_id')->nullable();
            $table->string('stripe_refund_id')->nullable();

            $table->string('status')->default('pending');
            $table->text('description')->nullable();
            $table->json('metadata')->nullable();

            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('campaign_transactions');
    }
};
