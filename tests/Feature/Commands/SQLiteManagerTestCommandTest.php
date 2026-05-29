<?php

declare(strict_types=1);

use Illuminate\Support\Facades\File;

test('it copies sqlite manager relationship test migrations', function (): void {
    $targets = File::glob(database_path('migrations/*_create_tests_table.php')) ?: [];
    $auditTarget = database_path('migrations/create_audit_log_table.php');
    $originalTargets = array_combine($targets, array_map(fn (string $target): string => File::get($target), $targets));
    $originalAudit = File::exists($auditTarget) ? File::get($auditTarget) : null;

    File::delete($targets);
    File::delete($auditTarget);

    try {
        $this->artisan('sqlite-manager:create-tests-table --no-migrate')
            ->expectsOutputToContain('Copied SQLite Manager test migration')
            ->expectsOutputToContain('Migration run skipped')
            ->assertSuccessful();

        $copiedTargets = File::glob(database_path('migrations/*_create_tests_table.php')) ?: [];
        $target = $copiedTargets[0] ?? '';

        expect(File::exists($target))->toBeTrue()
            ->and(basename((string) $target))->toMatch('/^\d{4}_\d{2}_\d{2}_\d{6}_create_tests_table\.php$/')
            ->and(File::get($target))->toContain('_lsm_test_users')
            ->and(File::get($target))->toContain('_lsm_test_posts')
            ->and(File::get($target))->toContain('_lsm_test_comments')
            ->and(File::exists($auditTarget))->toBeFalse();
    } finally {
        foreach (File::glob(database_path('migrations/*_create_tests_table.php')) ?: [] as $target) {
            File::delete($target);
        }

        foreach ($originalTargets as $target => $contents) {
            File::put($target, $contents);
        }

        if (is_string($originalAudit)) {
            File::put($auditTarget, $originalAudit);
        } else {
            File::delete($auditTarget);
        }
    }
});
