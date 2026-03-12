<?php declare(strict_types=1);

/**
 * Copyright (C) Brian Faust
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

use Cline\Settings\Support\SettingsRename;

describe('settings rename', function (): void {
    test('creates settings class rename instructions', function (): void {
        $rename = SettingsRename::settingsClass(
            'FromSettings',
            'ToSettings',
            'from.namespace',
            'to.namespace',
        );

        expect($rename->fromSettingsClass)->toBe('FromSettings')
            ->and($rename->toSettingsClass)->toBe('ToSettings')
            ->and($rename->matchesProperty())->toBeFalse()
            ->and($rename->targetProperty('apiToken'))->toBe('apiToken');
    });

    test('creates property rename instructions', function (): void {
        $rename = SettingsRename::property(
            'SettingsClass',
            'legacyFee',
            'deliveryFee',
            'settings.namespace',
        );

        expect($rename->matchesProperty())->toBeTrue()
            ->and($rename->fromProperty)->toBe('legacyFee')
            ->and($rename->toProperty)->toBe('deliveryFee')
            ->and($rename->targetProperty('legacyFee'))->toBe('deliveryFee');
    });
});
