<?php

declare(strict_types=1);

namespace Asawl\LaravelSqliteManager\Commands;

use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;
use Illuminate\Filesystem\Filesystem;

#[Description('Copy the audit_log migration into the current Laravel project and run it.')]
#[Signature('sqlite-manager:create-audit-log-table
        {--force : Overwrite the existing audit_log migration file and force the migration run.}
        {--no-migrate : Copy the migration file without running migrations.}')]
class SQLiteManagerCreateAuditLogTableCommand extends Command
{
    public function handle(Filesystem $filesystem): int
    {
        $source = dirname(__DIR__, 2).'/database/migrations/create_audit_log_table.php.stub';

        if (! $filesystem->exists($source)) {
            $this->components->error('The SQLite Manager audit_log migration stub could not be found.');

            return self::FAILURE;
        }

        $targetName = 'create_audit_log_table.php';
        $targetDirectory = database_path('migrations');
        $filesystem->ensureDirectoryExists($targetDirectory);

        /** @var list<string> $existingTargets */
        $existingTargets = $filesystem->glob($targetDirectory.'/*_create_audit_log_table.php') ?: [];
        $target = $existingTargets !== [] ? $existingTargets[0] : $targetDirectory.'/'.date('Y_m_d_His').'_'.$targetName;

        if ($existingTargets !== [] && ! $this->option('force')) {
            $this->components->warn("The audit_log migration already exists: {$target}");

            return self::SUCCESS;
        }

        $filesystem->copy($source, $target);
        $this->components->info("Copied audit_log migration: {$target}");

        if ($this->option('no-migrate')) {
            $this->components->info('Migration run skipped.');

            return self::SUCCESS;
        }

        $this->components->info('Running the audit_log migration.');

        return (int) $this->call('migrate', [
            '--force' => (bool) $this->option('force'),
        ]);
    }
}
