<?php declare(strict_types=1);

/**
 * Copyright (C) Brian Faust
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

use Cline\Settings\Exceptions\SettingsRenameConflictException;
use Cline\Settings\Support\Reference;
use Cline\Settings\Support\ResolutionTarget;
use Tests\Fixtures\Settings\ShipmentPricingSettings;

describe('settings rename conflict exception', function (): void {
    test('creates the target conflict message', function (): void {
        $exception = SettingsRenameConflictException::forTarget(
            ShipmentPricingSettings::class,
            'callBeforeDeliveryFee',
            new ResolutionTarget(
                new Reference('carrier', '1'),
                new Reference('user', '2'),
            ),
        );

        expect($exception->getMessage())->toContain(ShipmentPricingSettings::class)
            ->toContain('callBeforeDeliveryFee')
            ->toContain('carrier:1')
            ->toContain('user:2');
    });
});
