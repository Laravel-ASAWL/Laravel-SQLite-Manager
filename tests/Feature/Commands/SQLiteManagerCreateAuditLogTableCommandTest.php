<?php

declare(strict_types=1);

use Illuminate\Support\Facades\File;

beforeEach(function (): void {
    $this->existingTargets = File::glob(database_path('migrations/*_create_audit_log_table.php')) ?: [];
    $this->originalTargets = array_combine($this->existingTargets, array_map(fn (string $target): string => File::get($target), $this->existingTargets));

    File::delete($this->existingTargets);
});

afterEach(function (): void {
    foreach (File::glob(database_path('migrations/*_create_audit_log_table.php')) ?: [] as $target) {
        File::delete($target);
    }

    foreach ($this->originalTargets as $target => $contents) {
        File::put($target, $contents);
    }
});

test('it copies the audit log migration', function (): void {
    $this->artisan('sqlite-manager:create-audit-log-table --no-migrate')
        ->expectsOutputToContain('Copied audit_log migration')
        ->expectsOutputToContain('Migration run skipped')
        ->assertSuccessful();

    $copied = File::glob(database_path('migrations/*_create_audit_log_table.php')) ?: [];
    $target = $copied[0] ?? '';

    expect(File::exists($target))->toBeTrue()
        ->and(basename((string) $target))->toMatch('/^\d{4}_\d{2}_\d{2}_\d{6}_create_audit_log_table\.php$/')
        ->and(File::get($target))->toContain('_lsm_audit_log');
});

test('it skips when the audit log migration already exists', function (): void {
    $existing = database_path('migrations/2026_01_01_000000_create_audit_log_table.php');
    $originalContent = '<?php // existing migration stub';
    File::put($existing, $originalContent);

    $this->artisan('sqlite-manager:create-audit-log-table --no-migrate')
        ->assertSuccessful();

    expect(File::get($existing))->toBe($originalContent);
});

test('it overwrites an existing audit log migration with force', function (): void {
    $existing = database_path('migrations/2026_01_01_000000_create_audit_log_table.php');
    File::put($existing, '<?php // old stub');

    $this->artisan('sqlite-manager:create-audit-log-table --force --no-migrate')
        ->expectsOutputToContain('Copied audit_log migration')
        ->assertSuccessful();

    expect(File::get($existing))->toContain('_lsm_audit_log');
});
