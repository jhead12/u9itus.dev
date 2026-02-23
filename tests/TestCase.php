<?php

namespace Tests;

use Illuminate\Foundation\Http\Middleware\VerifyCsrfToken;
use Illuminate\Foundation\Testing\TestCase as BaseTestCase;

abstract class TestCase extends BaseTestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        // CSRF verification is irrelevant in a test context — HTTP test helpers
        // don't submit tokens, and session-based CSRF protection is a browser
        // concern.
        //
        // Two-layer defence used here because `runningUnitTests()` inside the
        // framework middleware depends on APP_ENV=testing being visible to the
        // running process, which can be shadowed by a .env file that declares
        // APP_ENV=local.  The static `except(['*'])` path is checked BEFORE
        // the `runningUnitTests()` gate so it always wins regardless of env
        // configuration.
        $this->withoutMiddleware(VerifyCsrfToken::class);
        VerifyCsrfToken::except(['*']);

        // Ensure Vite manifest exists for tests
        $this->ensureViteManifestExists();
    }

    protected function tearDown(): void
    {
        // Reset the static CSRF exception list so tests don't bleed into each
        // other when the test process is long-running.
        VerifyCsrfToken::flushState();

        parent::tearDown();
    }

    protected function ensureViteManifestExists(): void
    {
        $buildPath = public_path('build');
        $manifestPath = public_path('build/manifest.json');

        if (!file_exists($manifestPath)) {
            if (!is_dir($buildPath)) {
                mkdir($buildPath, 0755, true);
            }

            // Create a minimal manifest for testing
            file_put_contents(
                $manifestPath,
                json_encode([
                    'resources/js/app.js' => [
                        'file' => 'assets/app.js',
                        'src' => 'resources/js/app.js',
                    ],
                    'resources/css/app.css' => [
                        'file' => 'assets/app.css',
                        'src' => 'resources/css/app.css',
                    ],
                ])
            );
        }
    }
}
