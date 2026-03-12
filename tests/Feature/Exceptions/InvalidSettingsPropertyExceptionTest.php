<?php declare(strict_types=1);

/**
 * Copyright (C) Brian Faust
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

use Cline\Settings\Exceptions\InvalidSettingsPropertyException;
use Tests\Fixtures\Settings\ShipmentPricingSettings;

describe('invalid settings property exception', function (): void {
    test('creates the property message', function (): void {
        $exception = InvalidSettingsPropertyException::forProperty(
            ShipmentPricingSettings::class,
            'missingProperty',
        );

        expect($exception)->toBeInstanceOf(InvalidSettingsPropertyException::class)
            ->and($exception->getMessage())->toContain(ShipmentPricingSettings::class)
            ->toContain('missingProperty');
    });
});
