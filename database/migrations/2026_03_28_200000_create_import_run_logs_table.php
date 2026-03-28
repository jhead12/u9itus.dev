<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('import_run_logs', function (Blueprint $table) {
            $table->id();
            $table->string('command_name', 120);
            $table->string('source_url', 500)->nullable();
            $table->boolean('with_campaigns')->default(false);
            $table->boolean('dry_run')->default(false);
            $table->string('status', 20)->default('running');
            $table->integer('exit_code')->nullable();
            $table->unsignedInteger('created_count')->default(0);
            $table->unsignedInteger('updated_count')->default(0);
            $table->unsignedInteger('skipped_count')->default(0);
            $table->unsignedInteger('campaigns_created_count')->default(0);
            $table->timestamp('started_at')->nullable();
            $table->timestamp('finished_at')->nullable();
            $table->longText('output')->nullable();
            $table->text('error_message')->nullable();
            $table->timestamps();

            $table->index(['command_name', 'status']);
            $table->index('started_at');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('import_run_logs');
    }
};
