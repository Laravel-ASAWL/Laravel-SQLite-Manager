<?php

declare(strict_types=1);

namespace Asawl\LaravelSqliteManager\Commands;

use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;
use Illuminate\Filesystem\Filesystem;

#[Description('Copy SQLite Manager test migrations into the current Laravel project and run them.')]
#[Signature('sqlite-manager:create-tests-table
        {--force : Overwrite existing SQLite Manager test migration files and force the migration run.}
        {--no-migrate : Copy the test migration files without running migrations.}')]
class SQLiteManagerCreateTestsTableCommand extends Command
{
    public function handle(Filesystem $filesystem): int
    {
        $copied = $this->copyMigrations($filesystem);

        if ($copied === self::FAILURE) {
            return self::FAILURE;
        }

        if ($this->option('no-migrate')) {
            $this->components->info('SQLite Manager test migrations were copied. Migration run skipped.');

            return self::SUCCESS;
        }

        $this->components->info('Running SQLite Manager test migrations.');

        return (int) $this->call('migrate', [
            '--force' => (bool) $this->option('force'),
        ]);
    }

    private function copyMigrations(Filesystem $filesystem): int
    {
        $sourceDirectory = dirname(__DIR__, 2).'/database/migrations';
        $targetDirectory = database_path('migrations');

        /** @var list<string> $migrations */
        $migrations = $filesystem->glob($sourceDirectory.'/create_tests_*.php.stub') ?: [];

        $filesystem->ensureDirectoryExists($targetDirectory);
        sort($migrations);

        if ($migrations === []) {
            $this->components->error('No SQLite Manager test migration stubs were found.');

            return self::FAILURE;
        }

        foreach ($migrations as $source) {
            $migration = basename($source);
            $targetName = mb_substr($migration, 0, -5);

            /** @var list<string> $existingTargets */
            $existingTargets = $filesystem->glob($targetDirectory.'/*_'.$targetName) ?: [];
            $target = $existingTargets !== [] ? $existingTargets[0] : $targetDirectory.'/'.date('Y_m_d_His').'_'.$targetName;

            if (! $filesystem->exists($source)) {
                $this->components->error("The SQLite Manager test migration stub could not be found: {$migration}");

                return self::FAILURE;
            }

            if ($existingTargets !== [] && ! $this->option('force')) {
                $this->components->warn("SQLite Manager test migration already exists: {$target}");

                continue;
            }

            $filesystem->copy($source, $target);
            $this->components->info("Copied SQLite Manager test migration: {$target}");
        }

        return self::SUCCESS;
    }
}
