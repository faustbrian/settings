<?php declare(strict_types=1);

/**
 * Copyright (C) Brian Faust
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

use Cline\Settings\Facades\Settings;
use Tests\Fixtures\Models\BusinessEntity;
use Tests\Fixtures\Models\User;
use Tests\Fixtures\Settings\ShipmentPricingSettings;

describe('prune settings command', function (): void {
    test('reports zero removed rows when nothing is stale', function (): void {
        $businessEntity = BusinessEntity::query()->create([
            'name' => 'Finland',
            'country_code' => 'FI',
        ]);
        $subject = User::query()->create(['name' => 'Alice']);

        Settings::for($subject)->ownedBy($businessEntity)->save(
            new ShipmentPricingSettings(
                callBeforeDeliveryFee: 3.5,
                returnFreightDocFee: 0.3,
            ),
        );

        $this->artisan('settings:prune', ['--settings' => ShipmentPricingSettings::class])
            ->expectsOutputToContain('0 stale rows removed')
            ->assertSuccessful();
    });
});
