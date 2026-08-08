<?php

declare(strict_types=1);

/**
 * Docker deploy smoke tests (Step 111 / AC #34).
 *
 * Runs on the host when the stack is up. In-container checks use deploy/smoke-check-app.php
 * because the production Docker image excludes Pest dev dependencies.
 *
 * Run: ./deploy/smoke-test.sh
 */
describe('docker deploy smoke (host documentation)', function () {
    test('smoke entrypoint script exists', function () {
        expect(base_path('deploy/smoke-test.sh'))->toBeFile();
        expect(base_path('deploy/smoke-check-app.php'))->toBeFile();
    });
})->group('docker-smoke');
