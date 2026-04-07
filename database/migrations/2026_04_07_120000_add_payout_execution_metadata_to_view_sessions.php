<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('view_sessions', function (Blueprint $table) {
            $table->string('processor_selected', 50)->nullable()->after('paid_at');
            $table->string('processor_executed', 50)->nullable()->after('processor_selected');
            $table->string('processor_reference', 255)->nullable()->after('processor_executed');
            $table->decimal('processor_fee', 8, 2)->default(0)->after('processor_reference');

            $table->index(['processor_executed', 'paid_at'], 'view_sessions_processor_executed_paid_at_idx');
            $table->index('processor_selected', 'view_sessions_processor_selected_idx');
            $table->index('processor_reference', 'view_sessions_processor_reference_idx');
        });

    }

    public function down(): void
    {
        Schema::table('view_sessions', function (Blueprint $table) {
            $table->dropIndex('view_sessions_processor_executed_paid_at_idx');
            $table->dropIndex('view_sessions_processor_selected_idx');
            $table->dropIndex('view_sessions_processor_reference_idx');

            $table->dropColumn([
                'processor_selected',
                'processor_executed',
                'processor_reference',
                'processor_fee',
            ]);
        });
    }
};
