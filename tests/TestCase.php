<?php

namespace Tests;

use Illuminate\Foundation\Testing\TestCase as BaseTestCase;

abstract class TestCase extends BaseTestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        // Ensure Vite manifest exists for tests
        $this->ensureViteManifestExists();
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
