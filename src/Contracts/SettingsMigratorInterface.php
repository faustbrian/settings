<?php declare(strict_types=1);

/**
 * Copyright (C) Brian Faust
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Cline\Settings\Contracts;

use Cline\Settings\Migrations\SettingsBlueprint;
use Cline\Settings\Support\ResolutionTarget;
use Cline\Settings\Support\SettingsRename;
use Closure;

/**
 * @author Brian Faust <brian@cline.sh>
 */
interface SettingsMigratorInterface
{
    /**
     * @param class-string $settingsClass
     */
    public function for(string $settingsClass): SettingsBlueprint;

    /**
     * @param class-string $settingsClass
     */
    public function add(string $settingsClass, string $property, mixed $value, ResolutionTarget $target): void;

    /**
     * @param class-string $settingsClass
     */
    public function set(string $settingsClass, string $property, mixed $value, ResolutionTarget $target): void;

    /**
     * @param class-string $settingsClass
     */
    public function update(string $settingsClass, string $property, Closure $callback, ResolutionTarget $target): void;

    /**
     * @param class-string $settingsClass
     */
    public function delete(string $settingsClass, string $property, ResolutionTarget $target): void;

    /**
     * @param class-string $settingsClass
     */
    public function deleteIfExists(string $settingsClass, string $property, ResolutionTarget $target): void;

    /**
     * @param class-string $settingsClass
     */
    public function renameAtTarget(
        string $settingsClass,
        string $fromProperty,
        string $toProperty,
        ResolutionTarget $target,
    ): void;

    /**
     * @param class-string $settingsClass
     */
    public function exists(string $settingsClass, string $property, ResolutionTarget $target): bool;

    public function rename(SettingsRename $rename): int;

    /**
     * @param class-string $fromSettingsClass
     * @param class-string $toSettingsClass
     */
    public function renameSettings(
        string $fromSettingsClass,
        string $toSettingsClass,
        ?string $fromNamespace = null,
        ?string $toNamespace = null,
    ): int;

    /**
     * @param class-string $settingsClass
     */
    public function renameProperty(
        string $settingsClass,
        string $fromProperty,
        string $toProperty,
        ?string $namespace = null,
    ): int;
}
