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
        Schema::create('politician_credits', function (Blueprint $table) {
            $table->bigIncrements('id');
            $table->uuid('uuid')->nullable()->unique();
            $table->foreignId('politician_id')->constrained('politicians')->cascadeOnDelete();

            $table->string('transaction_type'); // purchase, usage, refund, adjustment, transfer
            $table->decimal('amount', 12, 2); // positive for additions, negative for deductions
            $table->decimal('balance_after', 12, 2);

            $table->foreignId('campaign_id')->nullable()->constrained('political_campaigns')->nullOnDelete();
            $table->foreignId('related_transaction_id')->nullable()->constrained('politician_credits')->nullOnDelete();

            $table->text('description')->nullable();
            $table->json('metadata')->nullable();

            $table->timestamp('created_at')->useCurrent();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('politician_credits');
    }
};
