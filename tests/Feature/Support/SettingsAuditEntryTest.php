<?php declare(strict_types=1);

/**
 * Copyright (C) Brian Faust
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

use Carbon\CarbonImmutable;
use Cline\Settings\Support\Reference;
use Cline\Settings\Support\ResolutionTarget;
use Cline\Settings\Support\SettingsAuditEntry;

describe('settings audit entry', function (): void {
    test('serializes audit entry data to arrays', function (): void {
        $entry = new SettingsAuditEntry(
            1,
            'saved',
            'SettingsClass',
            'settings.namespace',
            'apiToken',
            new ResolutionTarget(
                new Reference('carrier', '123'),
            ),
            new Reference('user', '456'),
            'old',
            'new',
            CarbonImmutable::parse('2026-03-12T00:00:00+00:00'),
        );

        expect($entry->toArray())->toMatchArray([
            'id' => 1,
            'action' => 'saved',
            'settings_class' => 'SettingsClass',
            'settings_namespace' => 'settings.namespace',
            'property' => 'apiToken',
            'old_value' => 'old',
            'new_value' => 'new',
            'created_at' => '2026-03-12T00:00:00+00:00',
        ]);
    });
});
