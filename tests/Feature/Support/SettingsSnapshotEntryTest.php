<?php declare(strict_types=1);

/**
 * Copyright (C) Brian Faust
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

use Carbon\CarbonImmutable;
use Cline\Settings\Support\ResolutionTarget;
use Cline\Settings\Support\SettingsSnapshotEntry;

describe('settings snapshot entry', function (): void {
    test('reconstitutes entries from arrays with defaults', function (): void {
        $entry = SettingsSnapshotEntry::fromArray([
            'settings_class' => 'SettingsClass',
            'settings_namespace' => 'settings.namespace',
            'property' => 'apiToken',
            'value' => 'secret',
        ]);

        expect($entry->settingsClass)->toBe('SettingsClass')
            ->and($entry->namespace)->toBe('settings.namespace')
            ->and($entry->property)->toBe('apiToken')
            ->and($entry->version)->toBe(1)
            ->and($entry->target->owner)->toBeNull();
    });

    test('serializes normalized values to arrays', function (): void {
        $entry = new SettingsSnapshotEntry(
            'SettingsClass',
            'settings.namespace',
            'rotatesAt',
            CarbonImmutable::parse('2026-03-12T00:00:00+00:00'),
            ResolutionTarget::app(),
            3,
        );

        expect($entry->toArray())->toMatchArray([
            'settings_class' => 'SettingsClass',
            'settings_namespace' => 'settings.namespace',
            'property' => 'rotatesAt',
            'value' => '2026-03-12T00:00:00+00:00',
            'version' => 3,
        ]);
    });
});
