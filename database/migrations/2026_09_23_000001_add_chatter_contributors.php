<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Spatie\Permission\Models\Role;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('politician_chatter_items', function (Blueprint $table) {
            $table->foreignId('submitted_by_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->text('contributor_notes')->nullable();
            $table->text('source_excerpt')->nullable();
        });
        Role::findOrCreate(\App\Support\ChatterContributorAccess::ROLE, 'web');
    }

    public function down(): void
    {
        Schema::table('politician_chatter_items', function (Blueprint $table) {
            $table->dropConstrainedForeignId('submitted_by_user_id');
            $table->dropColumn(['contributor_notes', 'source_excerpt']);
        });
        // Preserve role assignments; rolling back must not modify unrelated user access.
    }
};
