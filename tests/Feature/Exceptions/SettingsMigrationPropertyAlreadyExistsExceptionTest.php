<?php declare(strict_types=1);

/**
 * Copyright (C) Brian Faust
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

use Cline\Settings\Exceptions\SettingsMigrationPropertyAlreadyExistsException;
use Tests\Fixtures\Settings\ShipmentPricingSettings;

describe('settings migration property already exists exception', function (): void {
    test('creates the property message', function (): void {
        $exception = SettingsMigrationPropertyAlreadyExistsException::forProperty(
            ShipmentPricingSettings::class,
            'callBeforeDeliveryFee',
        );

        expect($exception->getMessage())->toContain(ShipmentPricingSettings::class)
            ->toContain('callBeforeDeliveryFee')
            ->toContain('already exists');
    });
});
