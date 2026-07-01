<?php

use App\Services\FECService;
use Illuminate\Support\Facades\Http;

it('returns empty array from getCommitteeContributions when service is not configured', function () {
    config(['services.fec.api_key' => null]);

    $service = new FECService();

    expect($service->getCommitteeContributions('C00123456'))->toBe([]);
});

it('maps FEC Schedule A committee contributions to name/total pairs', function () {
    config(['services.fec.api_key' => 'DEMO_KEY']);

    Http::fake([
        'api.open.fec.gov/v1/schedules/schedule_a/*' => Http::response([
            'results' => [
                ['contributor_name' => 'AIPAC', 'contribution_receipt_amount' => 5000],
                ['contributor_name' => 'National Association of Realtors', 'contribution_receipt_amount' => 2500],
            ],
        ], 200),
    ]);

    $service = new FECService();

    $result = $service->getCommitteeContributions('C00123456');

    expect($result)->toBe([
        ['name' => 'AIPAC', 'total' => '$5,000'],
        ['name' => 'National Association of Realtors', 'total' => '$2,500'],
    ]);

    Http::assertSent(function ($request) {
        return str_contains($request->url(), 'schedules/schedule_a')
            && $request['contributor_type'] === 'committee'
            && $request['committee_id'] === ['C00123456'];
    });
});

it('returns empty array when the Schedule A request fails', function () {
    config(['services.fec.api_key' => 'DEMO_KEY']);

    Http::fake([
        'api.open.fec.gov/v1/schedules/schedule_a/*' => Http::response([], 500),
    ]);

    $service = new FECService();

    expect($service->getCommitteeContributions('C00123456'))->toBe([]);
});

it('classifies FEC-sourced committee contributions through PacAffiliationClassifier', function () {
    config(['services.fec.api_key' => 'DEMO_KEY']);

    Http::fake([
        'api.open.fec.gov/v1/schedules/schedule_a/*' => Http::response([
            'results' => [
                ['contributor_name' => 'NORPAC', 'contribution_receipt_amount' => 10000],
            ],
        ], 200),
    ]);

    $service = new FECService();
    $classifier = new App\Services\PacAffiliationClassifier();

    $contributions = $service->getCommitteeContributions('C00123456');
    $matches = $classifier->classify($contributions);

    expect($matches)->toHaveCount(1);
    expect($matches[0]['group'])->toBe('aipac_pro_israel');
    expect($matches[0]['matched_name'])->toBe('NORPAC');
});
