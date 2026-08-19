<?php

use App\Models\CandidateLead;

test('candidate_leads.reason accepts LLM-generated explanations longer than 255 characters', function () {
    $lead = CandidateLead::create([
        'source_key' => 'rss_google_news',
        'full_name' => 'Test Candidate',
        'state' => 'LA',
        'source_url' => 'https://example.com/article',
        'source_hash' => hash('sha256', 'https://example.com/article'),
        'status' => CandidateLead::STATUS_PENDING,
        'discovered_at' => now(),
    ]);

    $longReason = str_repeat('This is a long LLM-generated rejection reason explaining the mismatch in detail. ', 5);
    expect(strlen($longReason))->toBeGreaterThan(255);

    $lead->update([
        'status' => CandidateLead::STATUS_REJECTED,
        'reason' => $longReason,
        'confidence' => 0.05,
        'resolved_at' => now(),
    ]);

    expect($lead->refresh()->reason)->toBe($longReason);
});
