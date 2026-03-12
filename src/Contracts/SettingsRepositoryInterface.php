<?php declare(strict_types=1);

/**
 * Copyright (C) Brian Faust
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Cline\Settings\Contracts;

use Cline\Settings\Support\ResolutionTarget;
use Cline\Settings\Support\SettingsQuery;
use Cline\Settings\Support\StoredSettingRecord;
use DateTimeInterface;

/**
 * @author Brian Faust <brian@cline.sh>
 */
interface SettingsRepositoryInterface
{
    /**
     * @param class-string $settingsClass
     */
    public function find(
        string $settingsClass,
        string $namespace,
        string $property,
        ResolutionTarget $target,
    ): ?StoredSettingRecord;

    /**
     * @param class-string         $settingsClass
     * @param array<string, mixed> $payload
     */
    public function save(
        string $settingsClass,
        string $namespace,
        string $property,
        array $payload,
        ResolutionTarget $target,
        ?int $expectedVersion = null,
    ): StoredSettingRecord;

    /**
     * @param class-string $settingsClass
     */
    public function delete(
        string $settingsClass,
        string $namespace,
        string $property,
        ResolutionTarget $target,
    ): bool;

    /**
     * @param class-string $settingsClass
     */
    public function purge(
        string $settingsClass,
        string $namespace,
        ResolutionTarget $target,
    ): int;

    /**
     * @return array<int, StoredSettingRecord>
     */
    public function all(SettingsQuery $query = new SettingsQuery()): array;

    public function prune(DateTimeInterface $before, SettingsQuery $query = new SettingsQuery()): int;
}
