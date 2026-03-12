<?php declare(strict_types=1);

/**
 * Copyright (C) Brian Faust
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

use Cline\Settings\Contracts\SettingsManagerInterface;
use Cline\Settings\Exceptions\SettingsMigrationPropertyAlreadyExistsException;
use Cline\Settings\Exceptions\SettingsMigrationPropertyDoesNotExistException;
use Cline\Settings\Migrations\SettingsBlueprint;
use Cline\Settings\Migrations\SettingsMigrator;
use Cline\Settings\Support\ResolutionTarget;
use Cline\Settings\Support\SettingsRename;
use Cline\Settings\Support\StoredValue;

describe('settings migrator', function (): void {
    test('creates blueprints and forwards rename operations', function (): void {
        $manager = Mockery::mock(SettingsManagerInterface::class);
        $manager->shouldReceive('rename')->twice()->andReturn(1);

        $migrator = new SettingsMigrator($manager);

        expect($migrator->for('SettingsClass'))->toBeInstanceOf(SettingsBlueprint::class)
            ->and($migrator->rename(
                new SettingsRename('From', 'To'),
            ))->toBe(1)
            ->and($migrator->renameSettings('From', 'To'))->toBe(1);
    });

    test('adds updates deletes and renames at exact targets', function (): void {
        $target = ResolutionTarget::app();
        $storedValue = new StoredValue('SettingsClass', 'SettingsClass', 'apiToken', 'secret', $target, 1, false, null);
        $manager = Mockery::mock(SettingsManagerInterface::class);
        $manager->shouldReceive('inspect')->andReturn([$storedValue], [$storedValue], [$storedValue], [$storedValue], [], []);
        $manager->shouldReceive('setValue')->times(3);
        $manager->shouldReceive('forgetValue')->times(3);
        $manager->shouldReceive('rename')->once()->andReturn(1);

        $migrator = new SettingsMigrator($manager);

        $migrator->set('SettingsClass', 'apiToken', 'secret', $target);
        $migrator->update('SettingsClass', 'apiToken', static fn (): string => 'changed', $target);
        $migrator->delete('SettingsClass', 'apiToken', $target);
        $migrator->deleteIfExists('SettingsClass', 'apiToken', $target);
        $migrator->renameAtTarget('SettingsClass', 'apiToken', 'token', $target);

        expect($migrator->exists('SettingsClass', 'apiToken', $target))->toBeFalse()
            ->and($migrator->renameProperty('SettingsClass', 'old', 'new'))->toBe(1);
    });

    test('throws when add or delete preconditions fail', function (): void {
        $target = ResolutionTarget::app();
        $storedValue = new StoredValue('SettingsClass', 'SettingsClass', 'apiToken', 'secret', $target, 1, false, null);
        $manager = Mockery::mock(SettingsManagerInterface::class);
        $manager->shouldReceive('inspect')->andReturn([$storedValue], []);

        $migrator = new SettingsMigrator($manager);

        expect(function () use ($migrator, $target): void {
            $migrator->add('SettingsClass', 'apiToken', 'secret', $target);
        })
            ->toThrow(SettingsMigrationPropertyAlreadyExistsException::class)
            ->and(function () use ($migrator, $target): void {
                $migrator->delete('SettingsClass', 'apiToken', $target);
            })
            ->toThrow(SettingsMigrationPropertyDoesNotExistException::class);
    });
});
