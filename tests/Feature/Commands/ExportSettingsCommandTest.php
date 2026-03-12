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

describe('export settings command', function (): void {
    test('exports settings rows to the requested snapshot path', function (): void {
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

        $exportPath = __DIR__.'/../../Fixtures/exported-settings.json';

        $this->artisan('settings:export', ['path' => $exportPath])
            ->assertSuccessful();
    });
});
