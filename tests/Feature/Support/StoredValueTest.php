<?php declare(strict_types=1);

/**
 * Copyright (C) Brian Faust
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

use Carbon\CarbonImmutable;
use Cline\Settings\Support\ResolutionTarget;
use Cline\Settings\Support\StoredValue;

describe('stored value', function (): void {
    test('serializes decoded stored values to arrays', function (): void {
        $value = new StoredValue(
            'SettingsClass',
            'settings.namespace',
            'rotatesAt',
            CarbonImmutable::parse('2026-03-12T00:00:00+00:00'),
            ResolutionTarget::app(),
            4,
            true,
            'CastClass',
            CarbonImmutable::parse('2026-03-12T01:00:00+00:00'),
        );

        expect($value->toArray())->toMatchArray([
            'settings_class' => 'SettingsClass',
            'settings_namespace' => 'settings.namespace',
            'property' => 'rotatesAt',
            'value' => '2026-03-12T00:00:00+00:00',
            'version' => 4,
            'encrypted' => true,
            'cast' => 'CastClass',
            'updated_at' => '2026-03-12T01:00:00+00:00',
        ]);
    });
});
