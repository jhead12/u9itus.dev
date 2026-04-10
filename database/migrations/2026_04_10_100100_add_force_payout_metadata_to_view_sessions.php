<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('view_sessions', function (Blueprint $table) {
            $table->boolean('force_payout')->default(false)->after('processor_fee');
            $table->timestamp('force_payout_at')->nullable()->after('force_payout');
            $table->foreignId('force_payout_by_admin_id')->nullable()->after('force_payout_at')
                ->constrained('users')->nullOnDelete();
            $table->text('force_payout_reason')->nullable()->after('force_payout_by_admin_id');

            $table->index('force_payout', 'view_sessions_force_payout_idx');
        });
    }

    public function down(): void
    {
        Schema::table('view_sessions', function (Blueprint $table) {
            $table->dropIndex('view_sessions_force_payout_idx');
            $table->dropConstrainedForeignId('force_payout_by_admin_id');
            $table->dropColumn([
                'force_payout',
                'force_payout_at',
                'force_payout_reason',
            ]);
        });
    }
};
