<?php

declare(strict_types=1);

test('erd requirements review document exists and is approved', function () {
    $path = base_path('docs/design/erd-requirements-review.md');

    expect($path)->toBeReadableFile();

    $contents = file_get_contents($path);

    expect($contents)->toContain('Step 25 deliverable');
    expect($contents)->toContain('Approved with documented amendments');
    expect($contents)->toContain('REQ-001');
    expect($contents)->toContain('REQ-066');
    expect($contents)->toContain('organization_settings');
});

test('data model reflects step 25 review amendments', function () {
    $contents = file_get_contents(base_path('docs/design/data-model.md'));

    expect($contents)->toContain('erd-requirements-review.md');
    expect($contents)->toContain('Step 25 complete');
    expect($contents)->toContain('## organization_settings');
    expect($contents)->toContain('Administrative entities (Wave 1)');
    expect($contents)->toContain('organization_settings, audit_logs');
});

test('data model core constraints cover wave 1 requirements', function () {
    $contents = file_get_contents(base_path('docs/design/data-model.md'));

    expect($contents)->toContain('users.center_id` null for Owner role');
    expect($contents)->toContain('(center_id, normalization_policy_version, exact_canonical_hash)');
    expect($contents)->toContain('(center_id, business_date)');
    expect($contents)->toContain('(center_id, file_hash)');
});
