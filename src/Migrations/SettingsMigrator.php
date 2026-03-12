<?php declare(strict_types=1);

/**
 * Copyright (C) Brian Faust
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Cline\Settings\Migrations;

use Cline\Settings\Contracts\SettingsManagerInterface;
use Cline\Settings\Contracts\SettingsMigratorInterface;
use Cline\Settings\Exceptions\SettingsMigrationPropertyAlreadyExistsException;
use Cline\Settings\Exceptions\SettingsMigrationPropertyDoesNotExistException;
use Cline\Settings\Support\ResolutionTarget;
use Cline\Settings\Support\SettingsQuery;
use Cline\Settings\Support\SettingsRename;
use Cline\Settings\Support\StoredValue;
use Closure;

/**
 * High-level mutation API used inside settings migrations.
 *
 * The migrator delegates all persistence to the package's normal manager so
 * settings migrations inherit the same serialization, encryption, auditing,
 * and validation behavior as runtime writes.
 *
 * @author Brian Faust <brian@cline.sh>
 * @psalm-immutable
 */
final readonly class SettingsMigrator implements SettingsMigratorInterface
{
    public function __construct(
        private SettingsManagerInterface $manager,
    ) {}

    /**
     * @param class-string $settingsClass
     */
    public function for(string $settingsClass): SettingsBlueprint
    {
        return new SettingsBlueprint($this, $settingsClass);
    }

    /**
     * @param class-string $settingsClass
     */
    public function add(string $settingsClass, string $property, mixed $value, ResolutionTarget $target): void
    {
        if ($this->exists($settingsClass, $property, $target)) {
            throw SettingsMigrationPropertyAlreadyExistsException::forProperty($settingsClass, $property);
        }

        $this->manager->setValue($settingsClass, $property, $value, null, $target);
    }

    /**
     * @param class-string $settingsClass
     */
    public function set(string $settingsClass, string $property, mixed $value, ResolutionTarget $target): void
    {
        $this->manager->setValue($settingsClass, $property, $value, null, $target);
    }

    /**
     * @param class-string $settingsClass
     */
    public function update(
        string $settingsClass,
        string $property,
        Closure $callback,
        ResolutionTarget $target,
    ): void {
        $current = $this->current($settingsClass, $property, $target);

        if (!$current instanceof StoredValue) {
            throw SettingsMigrationPropertyDoesNotExistException::forProperty($settingsClass, $property);
        }

        $this->manager->setValue(
            $settingsClass,
            $property,
            $callback($current->value),
            null,
            $target,
        );
    }

    /**
     * @param class-string $settingsClass
     */
    public function delete(string $settingsClass, string $property, ResolutionTarget $target): void
    {
        if (!$this->exists($settingsClass, $property, $target)) {
            throw SettingsMigrationPropertyDoesNotExistException::forProperty($settingsClass, $property);
        }

        $this->manager->forgetValue($settingsClass, $property, null, $target);
    }

    /**
     * @param class-string $settingsClass
     */
    public function deleteIfExists(string $settingsClass, string $property, ResolutionTarget $target): void
    {
        if (!$this->exists($settingsClass, $property, $target)) {
            return;
        }

        $this->manager->forgetValue($settingsClass, $property, null, $target);
    }

    /**
     * @param class-string $settingsClass
     */
    public function renameAtTarget(
        string $settingsClass,
        string $fromProperty,
        string $toProperty,
        ResolutionTarget $target,
    ): void {
        $current = $this->current($settingsClass, $fromProperty, $target);

        if (!$current instanceof StoredValue) {
            throw SettingsMigrationPropertyDoesNotExistException::forProperty($settingsClass, $fromProperty);
        }

        if ($this->exists($settingsClass, $toProperty, $target)) {
            throw SettingsMigrationPropertyAlreadyExistsException::forProperty($settingsClass, $toProperty);
        }

        $this->manager->setValue($settingsClass, $toProperty, $current->value, null, $target);
        $this->manager->forgetValue($settingsClass, $fromProperty, null, $target);
    }

    /**
     * @param class-string $settingsClass
     */
    public function exists(string $settingsClass, string $property, ResolutionTarget $target): bool
    {
        return $this->current($settingsClass, $property, $target) instanceof StoredValue;
    }

    public function rename(SettingsRename $rename): int
    {
        return $this->manager->rename($rename);
    }

    /**
     * @param class-string $fromSettingsClass
     * @param class-string $toSettingsClass
     */
    public function renameSettings(
        string $fromSettingsClass,
        string $toSettingsClass,
        ?string $fromNamespace = null,
        ?string $toNamespace = null,
    ): int {
        return $this->manager->rename(SettingsRename::settingsClass(
            $fromSettingsClass,
            $toSettingsClass,
            $fromNamespace,
            $toNamespace,
        ));
    }

    /**
     * @param class-string $settingsClass
     */
    public function renameProperty(
        string $settingsClass,
        string $fromProperty,
        string $toProperty,
        ?string $namespace = null,
    ): int {
        return $this->manager->rename(SettingsRename::property(
            $settingsClass,
            $fromProperty,
            $toProperty,
            $namespace,
        ));
    }

    /**
     * @param class-string $settingsClass
     */
    private function current(string $settingsClass, string $property, ResolutionTarget $target): ?StoredValue
    {
        $storedValues = $this->manager->inspect(
            new SettingsQuery(
                settingsClass: $settingsClass,
                property: $property,
                target: $target,
            ),
        );

        return $storedValues[0] ?? null;
    }
}
