<?php declare(strict_types=1);

/**
 * Copyright (C) Brian Faust
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

use Cline\Settings\Facades\Settings;
use Cline\Settings\Support\SettingsAuditQuery;
use Tests\Fixtures\Models\Carrier;
use Tests\Fixtures\Models\User;
use Tests\Fixtures\Settings\CarrierCredentialSettings;

describe('audit settings command', function (): void {
    test('lists matching audit rows for a specific property', function (): void {
        $carrier = Carrier::query()->create(['name' => 'UPS']);
        $subject = User::query()->create(['name' => 'Bob']);

        Settings::for($subject)->ownedBy($carrier)->set(
            CarrierCredentialSettings::class,
            'apiToken',
            'initial',
        );

        Settings::for($subject)->ownedBy($carrier)->set(
            CarrierCredentialSettings::class,
            'apiToken',
            'changed',
        );

        expect(Settings::audit(
            new SettingsAuditQuery(
                action: 'saved',
                settingsClass: CarrierCredentialSettings::class,
                property: 'apiToken',
            ),
        ))->toHaveCount(2);

        $this->artisan('settings:audit', [
            '--settings' => CarrierCredentialSettings::class,
            '--property' => 'apiToken',
        ])->expectsOutputToContain('apiToken')
            ->assertSuccessful();
    });
});
