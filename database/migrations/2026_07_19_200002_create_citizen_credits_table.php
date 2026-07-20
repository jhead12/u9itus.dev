<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('citizen_credits', function (Blueprint $table) {
            $table->bigIncrements('id');
            $table->uuid('uuid')->nullable()->unique();
            $table->foreignId('citizen_id')->constrained('citizens')->cascadeOnDelete();

            $table->string('transaction_type'); // purchase, usage, refund, adjustment
            $table->decimal('amount', 12, 2); // positive for additions, negative for deductions
            $table->decimal('balance_after', 12, 2);

            $table->foreignId('citizen_campaign_id')->nullable()->constrained('citizen_campaigns')->nullOnDelete();
            $table->foreignId('related_transaction_id')->nullable()->constrained('citizen_credits')->nullOnDelete();

            $table->text('description')->nullable();
            $table->json('metadata')->nullable();

            $table->timestamp('created_at')->useCurrent();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('citizen_credits');
    }
};
