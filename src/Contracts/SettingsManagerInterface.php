<?php declare(strict_types=1);

/**
 * Copyright (C) Brian Faust
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Cline\Settings\Contracts;

use Cline\Settings\Conductors\ResolutionConductor;
use Cline\Settings\Support\ResolutionChain;
use Cline\Settings\Support\ResolutionTarget;
use Cline\Settings\Support\ResolvedSettings;
use Cline\Settings\Support\SettingsAuditEntry;
use Cline\Settings\Support\SettingsAuditQuery;
use Cline\Settings\Support\SettingsQuery;
use Cline\Settings\Support\SettingsRename;
use Cline\Settings\Support\SettingsRenameConflict;
use Cline\Settings\Support\SettingsSnapshot;
use Cline\Settings\Support\StoredValue;
use DateTimeInterface;

/**
 * @author Brian Faust <brian@cline.sh>
 */
interface SettingsManagerInterface
{
    public function for(mixed $subject): ResolutionConductor;

    /**
     * @param class-string $settingsClass
     */
    public function resolve(string $settingsClass, mixed $subject, ResolutionChain $chain): object;

    /**
     * @param class-string $settingsClass
     */
    public function resolveWithMetadata(string $settingsClass, mixed $subject, ResolutionChain $chain): ResolvedSettings;

    public function save(object $settings, mixed $subject, ResolutionTarget $target): object;

    /**
     * @param class-string $settingsClass
     */
    public function getValue(
        string $settingsClass,
        string $property,
        mixed $subject,
        ResolutionChain $chain,
        mixed $default = null,
    ): mixed;

    /**
     * @param class-string $settingsClass
     */
    public function setValue(
        string $settingsClass,
        string $property,
        mixed $value,
        mixed $subject,
        ResolutionTarget $target,
    ): void;

    /**
     * @param class-string $settingsClass
     */
    public function compareAndSetValue(
        string $settingsClass,
        string $property,
        mixed $value,
        mixed $subject,
        ResolutionTarget $target,
        ?int $expectedVersion = null,
    ): StoredValue;

    /**
     * @param class-string $settingsClass
     */
    public function forgetValue(
        string $settingsClass,
        string $property,
        mixed $subject,
        ResolutionTarget $target,
    ): void;

    /**
     * @param class-string $settingsClass
     */
    public function forgetSettings(
        string $settingsClass,
        mixed $subject,
        ResolutionTarget $target,
    ): void;

    /**
     * @return array<int, StoredValue>
     */
    public function inspect(SettingsQuery $query = new SettingsQuery()): array;

    public function export(SettingsQuery $query = new SettingsQuery()): SettingsSnapshot;

    public function import(SettingsSnapshot $snapshot, mixed $subject = null): void;

    public function rename(SettingsRename $rename, mixed $subject = null): int;

    /**
     * @return array<int, SettingsAuditEntry>
     */
    public function audit(SettingsAuditQuery $query = new SettingsAuditQuery()): array;

    /**
     * @return array<int, SettingsRenameConflict>
     */
    public function inspectRenameConflicts(SettingsRename $rename): array;

    public function replay(int $auditId, mixed $subject = null): void;

    public function rollback(int $auditId, mixed $subject = null): void;

    public function prune(DateTimeInterface $before, SettingsQuery $query = new SettingsQuery()): int;
}
