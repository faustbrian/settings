<?php declare(strict_types=1);

/**
 * Copyright (C) Brian Faust
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Cline\Settings\Migrations;

use Cline\Settings\Support\ResolutionTarget;
use Closure;

/**
 * Mutating DSL for one exact settings target inside a migration.
 *
 * Each method operates on the fixed settings class and target selected by the
 * parent blueprint, keeping migration files terse while still making scope
 * explicit.
 *
 * @author Brian Faust <brian@cline.sh>
 * @psalm-immutable
 */
final readonly class ScopedSettingsBlueprint
{
    /**
     * @param class-string $settingsClass
     */
    public function __construct(
        private SettingsMigrator $migrator,
        private string $settingsClass,
        private ResolutionTarget $target,
    ) {}

    public function add(string $property, mixed $value): self
    {
        $this->migrator->add($this->settingsClass, $property, $value, $this->target);

        return $this;
    }

    public function set(string $property, mixed $value): self
    {
        $this->migrator->set($this->settingsClass, $property, $value, $this->target);

        return $this;
    }

    public function update(string $property, Closure $callback): self
    {
        $this->migrator->update($this->settingsClass, $property, $callback, $this->target);

        return $this;
    }

    public function delete(string $property): self
    {
        $this->migrator->delete($this->settingsClass, $property, $this->target);

        return $this;
    }

    public function deleteIfExists(string $property): self
    {
        $this->migrator->deleteIfExists($this->settingsClass, $property, $this->target);

        return $this;
    }

    public function rename(string $from, string $to): self
    {
        $this->migrator->renameAtTarget($this->settingsClass, $from, $to, $this->target);

        return $this;
    }

    public function exists(string $property): bool
    {
        return $this->migrator->exists($this->settingsClass, $property, $this->target);
    }
}
