<?php declare(strict_types=1);

/**
 * Copyright (C) Brian Faust
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Cline\Settings\Contracts;

/**
 * @author Brian Faust <brian@cline.sh>
 */
interface SettingsMigrationRunnerInterface
{
    /**
     * @param array<int, string> $paths
     *
     * @return array<int, string>
     */
    public function run(array $paths = []): array;

    /**
     * @param array<int, string> $paths
     *
     * @return array<int, string>
     */
    public function rollback(int $steps = 1, array $paths = []): array;

    /**
     * @param array<int, string> $paths
     *
     * @return array<string, string>
     */
    public function migrationFiles(array $paths = []): array;
}
