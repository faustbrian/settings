<?php declare(strict_types=1);

/**
 * Copyright (C) Brian Faust
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

use Cline\Settings\Facades\Settings;
use Tests\Fixtures\Models\BusinessEntity;
use Tests\Fixtures\Models\Carrier;
use Tests\Fixtures\Models\User;
use Tests\Fixtures\Settings\ShipmentPricingSettings;

describe('resolve settings command', function (): void {
    test('resolves settings using explicit target and fallback chains', function (): void {
        $businessEntity = BusinessEntity::query()->create([
            'name' => 'Finland',
            'country_code' => 'FI',
        ]);
        $carrier = Carrier::query()->create(['name' => 'UPS']);
        $subject = User::query()->create(['name' => 'Alice']);

        Settings::for($subject)->ownedBy($businessEntity)->save(
            new ShipmentPricingSettings(
                callBeforeDeliveryFee: 3.5,
                returnFreightDocFee: 0.3,
            ),
        );

        Settings::for($subject)->ownedBy($carrier)->save(
            new ShipmentPricingSettings(
                callBeforeDeliveryFee: 8.0,
                returnFreightDocFee: 0.8,
            ),
        );

        $this->artisan('settings:resolve', [
            'settings' => ShipmentPricingSettings::class,
            '--target' => ['carrier:'.$carrier->getKey()],
            '--fallback' => ['business_entity:'.$businessEntity->getKey(), 'app'],
        ])->expectsOutputToContain('callBeforeDeliveryFee')
            ->assertSuccessful();
    });
});
