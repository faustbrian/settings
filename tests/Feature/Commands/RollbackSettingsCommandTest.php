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

describe('rollback settings command', function (): void {
    test('rolls back a saved audit row', function (): void {
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

        $auditId = Settings::audit(
            new SettingsAuditQuery(
                action: 'saved',
                settingsClass: CarrierCredentialSettings::class,
                property: 'apiToken',
            ),
        )[1]->id;

        $this->artisan('settings:rollback', [
            'audit' => $auditId,
        ])->assertSuccessful();
    });
});
