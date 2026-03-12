<?php declare(strict_types=1);

/**
 * Copyright (C) Brian Faust
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

use Cline\Settings\Contracts\SettingsManagerInterface;
use Cline\Settings\Migrations\ScopedSettingsBlueprint;
use Cline\Settings\Migrations\SettingsMigrator;
use Cline\Settings\Support\ResolutionTarget;
use Cline\Settings\Support\StoredValue;

describe('scoped settings blueprint', function (): void {
    test('forwards mutating operations to the migrator', function (): void {
        $target = ResolutionTarget::app();
        $storedValue = new StoredValue('SettingsClass', 'SettingsClass', 'apiToken', 'secret', $target, 1, false, null);
        $manager = Mockery::mock(SettingsManagerInterface::class);
        $manager->shouldReceive('inspect')->andReturn([], [$storedValue], [$storedValue], [$storedValue], [$storedValue], [], [$storedValue]);
        $manager->shouldReceive('setValue')->times(4);
        $manager->shouldReceive('forgetValue')->times(3);

        $migrator = new SettingsMigrator($manager);

        $blueprint = new ScopedSettingsBlueprint($migrator, 'SettingsClass', $target);

        expect($blueprint->add('apiToken', 'secret'))->toBe($blueprint)
            ->and($blueprint->set('apiToken', 'secret'))->toBe($blueprint)
            ->and($blueprint->update('apiToken', static fn (mixed $value): mixed => $value))->toBe($blueprint)
            ->and($blueprint->delete('apiToken'))->toBe($blueprint)
            ->and($blueprint->deleteIfExists('apiToken'))->toBe($blueprint)
            ->and($blueprint->rename('old', 'new'))->toBe($blueprint)
            ->and($blueprint->exists('apiToken'))->toBeTrue();
    });
});
