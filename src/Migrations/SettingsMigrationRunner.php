<?php declare(strict_types=1);

/**
 * Copyright (C) Brian Faust
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Cline\Settings\Migrations;

use Cline\Settings\Contracts\SettingsMigrationRepositoryInterface;
use Cline\Settings\Contracts\SettingsMigrationRunnerInterface;
use Cline\Settings\Exceptions\SettingsMigrationFileDidNotReturnMigrationException;
use Illuminate\Filesystem\Filesystem;

use const PATHINFO_FILENAME;

use function array_diff_key;
use function array_filter;
use function array_flip;
use function array_values;
use function config;
use function is_array;
use function is_string;
use function ksort;
use function pathinfo;

/**
 * Runs tracked settings migration files from configured paths.
 *
 * Each migration file is resolved independently and must return a
 * `AbstractSettingsMigration` instance, typically an anonymous class. Applied
 * migrations are recorded by filename so re-runs only execute pending files.
 *
 * @author Brian Faust <brian@cline.sh>
 * @psalm-immutable
 */
final readonly class SettingsMigrationRunner implements SettingsMigrationRunnerInterface
{
    public function __construct(
        private Filesystem $files,
        private SettingsMigrationRepositoryInterface $repository,
    ) {}

    /**
     * @param array<int, string> $paths
     *
     * @return array<int, string>
     */
    public function run(array $paths = []): array
    {
        $files = $this->migrationFiles($paths);
        $pending = array_diff_key($files, array_flip($this->repository->ran()));

        if ($pending === []) {
            return [];
        }

        $batch = $this->repository->nextBatchNumber();
        $ran = [];

        foreach ($pending as $migration => $path) {
            $this->resolvePath($path)->up();
            $this->repository->log($migration, $batch);
            $ran[] = $migration;
        }

        return $ran;
    }

    /**
     * @param array<int, string> $paths
     *
     * @return array<int, string>
     */
    public function rollback(int $steps = 1, array $paths = []): array
    {
        $files = $this->migrationFiles($paths);
        $rolledBack = [];

        foreach ($this->repository->last($steps) as $migration) {
            if (!isset($files[$migration])) {
                continue;
            }

            $this->resolvePath($files[$migration])->down();
            $this->repository->delete($migration);
            $rolledBack[] = $migration;
        }

        return $rolledBack;
    }

    /**
     * @param array<int, string> $paths
     *
     * @return array<string, string>
     */
    public function migrationFiles(array $paths = []): array
    {
        $migrationFiles = [];

        foreach ($this->paths($paths) as $path) {
            foreach ($this->files->glob($path.'/*.php') as $file) {
                if (!is_string($file)) {
                    continue;
                }

                $migrationFiles[pathinfo($file, PATHINFO_FILENAME)] = $file;
            }
        }

        ksort($migrationFiles);

        return $migrationFiles;
    }

    /**
     * @param array<int, string> $paths
     *
     * @return array<int, string>
     */
    private function paths(array $paths): array
    {
        if ($paths !== []) {
            return array_values(array_filter($paths, static fn (string $path): bool => $path !== ''));
        }

        $configured = config('settings.migrations.paths', []);

        if (!is_array($configured)) {
            return [];
        }

        return array_values(array_filter($configured, static fn (mixed $path): bool => is_string($path) && $path !== ''));
    }

    private function resolvePath(string $path): AbstractSettingsMigration
    {
        $migration = $this->files->getRequire($path);

        if ($migration instanceof AbstractSettingsMigration) {
            return $migration;
        }

        throw SettingsMigrationFileDidNotReturnMigrationException::fromPath($path);
    }
}
