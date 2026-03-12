<?php declare(strict_types=1);

/**
 * Copyright (C) Brian Faust
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

use Cline\Settings\Contracts\SettingsManagerInterface;
use Cline\Settings\Migrations\ScopedSettingsBlueprint;
use Cline\Settings\Migrations\SettingsBlueprint;
use Cline\Settings\Migrations\SettingsMigrator;
use Tests\Fixtures\Models\Carrier;
use Tests\Fixtures\Models\User;

describe('settings blueprint', function (): void {
    test('creates scoped blueprints for app and owned targets', function (): void {
        $carrier = Carrier::query()->create(['name' => 'DHL']);
        $user = User::query()->create(['name' => 'Alice']);

        $blueprint = new SettingsBlueprint(
            new SettingsMigrator(Mockery::mock(SettingsManagerInterface::class)),
            'SettingsClass',
        );

        expect($blueprint->app())->toBeInstanceOf(ScopedSettingsBlueprint::class)
            ->and($blueprint->ownedBy($carrier, $user))->toBeInstanceOf(ScopedSettingsBlueprint::class);
    });
});
