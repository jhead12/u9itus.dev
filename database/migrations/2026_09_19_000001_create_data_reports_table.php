<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('data_reports', function (Blueprint $table) {
            $table->id();
            // What was reported. subject_id is deliberately not a foreign key:
            // most map candidates are scraped election_candidate_records with
            // no politician row, and a report must survive that row being pruned.
            $table->string('subject_type', 32);            // politician|election_candidate_record|ballot_measure|other
            $table->unsignedBigInteger('subject_id')->nullable();
            $table->string('subject_name', 191)->nullable(); // name as the reporter saw it
            $table->string('subject_office', 191)->nullable(); // seat as the reporter saw it, e.g. "Governor" or "CA-38"
            $table->string('state', 2)->nullable();
            $table->string('problem', 32);                 // wrong_name|not_a_person|duplicate|wrong_party|wrong_office|wrong_dates|outdated|other
            $table->text('message')->nullable();
            $table->string('page_url', 500)->nullable();
            $table->string('source_label', 100)->nullable(); // the stamp the reporter saw
            $table->string('reporter_hash', 64)->nullable(); // sha256(ip + app key), never the raw IP
            $table->string('status', 16)->default('pending'); // pending|resolved|dismissed
            $table->foreignId('resolved_by_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('resolved_at')->nullable();
            $table->text('resolution_note')->nullable();
            $table->timestamps();

            $table->index('status');
            $table->index(['subject_type', 'subject_id']);
            $table->index('state');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('data_reports');
    }
};
