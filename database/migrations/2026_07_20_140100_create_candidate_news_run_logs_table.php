<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('candidate_news_run_logs', function (Blueprint $table) {
            $table->id();
            $table->string('command_name', 120);
            $table->string('status', 20)->default('running');
            $table->integer('exit_code')->nullable();
            $table->unsignedInteger('queued_count')->default(0);
            $table->unsignedInteger('refreshed_count')->default(0);
            $table->unsignedInteger('failed_count')->default(0);
            $table->timestamp('started_at')->nullable();
            $table->timestamp('finished_at')->nullable();
            $table->text('error_message')->nullable();
            $table->timestamps();

            $table->index(['command_name', 'status']);
            $table->index('started_at');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('candidate_news_run_logs');
    }
};
