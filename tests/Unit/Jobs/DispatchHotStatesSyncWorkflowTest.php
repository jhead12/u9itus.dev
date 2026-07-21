<?php

use App\Jobs\DispatchHotStatesSyncWorkflow;
use Illuminate\Support\Facades\Http;

test('dispatches workflow_dispatch calls for all three state-scoped workflows', function () {
    config([
        'services.github.hotstate_token' => 'test-token',
        'services.github.repo' => 'HeadEnterprises/u9itus.dev',
        'services.github.ref' => 'master',
    ]);

    Http::fake([
        'api.github.com/*' => Http::response('', 204),
    ]);

    (new DispatchHotStatesSyncWorkflow(['CA', 'TX']))->handle();

    Http::assertSent(fn ($request) => $request->url() === 'https://api.github.com/repos/HeadEnterprises/u9itus.dev/actions/workflows/refresh-map-candidates.yml/dispatches'
        && $request['ref'] === 'master'
        && $request['inputs']['states'] === 'CA,TX'
    );

    Http::assertSent(fn ($request) => $request->url() === 'https://api.github.com/repos/HeadEnterprises/u9itus.dev/actions/workflows/sync-census-demographics.yml/dispatches'
        && $request['inputs']['states'] === 'CA,TX'
    );

    Http::assertSent(fn ($request) => $request->url() === 'https://api.github.com/repos/HeadEnterprises/u9itus.dev/actions/workflows/sync-candidates.yml/dispatches'
        && $request['inputs']['states'] === 'CA,TX'
    );
});

test('skips dispatch entirely when github credentials are not configured', function () {
    config([
        'services.github.hotstate_token' => null,
        'services.github.repo' => null,
    ]);

    Http::fake();

    (new DispatchHotStatesSyncWorkflow(['CA']))->handle();

    Http::assertNothingSent();
});

test('does nothing when given an empty state list', function () {
    config([
        'services.github.hotstate_token' => 'test-token',
        'services.github.repo' => 'HeadEnterprises/u9itus.dev',
    ]);

    Http::fake();

    (new DispatchHotStatesSyncWorkflow([]))->handle();

    Http::assertNothingSent();
});
