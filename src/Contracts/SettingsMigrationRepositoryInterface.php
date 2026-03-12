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
interface SettingsMigrationRepositoryInterface
{
    /**
     * @return array<int, string>
     */
    public function ran(): array;

    public function log(string $migration, int $batch): void;

    public function delete(string $migration): void;

    public function nextBatchNumber(): int;

    /**
     * @return array<int, string>
     */
    public function last(int $steps = 1): array;
}
