<?php declare(strict_types=1);

/**
 * Copyright (C) Brian Faust
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

use Carbon\CarbonImmutable;
use Cline\Settings\Support\ResolutionTarget;
use Cline\Settings\Support\StoredSettingRecord;

describe('stored setting record', function (): void {
    test('stores raw repository record data', function (): void {
        $record = new StoredSettingRecord(
            'SettingsClass',
            'settings.namespace',
            'apiToken',
            ['data' => 'secret'],
            ResolutionTarget::app(),
            2,
            CarbonImmutable::parse('2026-03-12T00:00:00+00:00'),
        );

        expect($record->settingsClass)->toBe('SettingsClass')
            ->and($record->namespace)->toBe('settings.namespace')
            ->and($record->property)->toBe('apiToken')
            ->and($record->payload)->toBe(['data' => 'secret'])
            ->and($record->version)->toBe(2);
    });
});
