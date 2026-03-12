<?php declare(strict_types=1);

/**
 * Copyright (C) Brian Faust
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

use Cline\Settings\Support\Reference;
use Cline\Settings\Support\ResolutionTarget;
use Cline\Settings\Support\SettingsRenameConflict;

describe('settings rename conflict', function (): void {
    test('serializes rename conflict previews to arrays', function (): void {
        $conflict = new SettingsRenameConflict(
            'FromSettings',
            'from.namespace',
            'legacyFee',
            'ToSettings',
            'to.namespace',
            'deliveryFee',
            new ResolutionTarget(
                new Reference('carrier', '123'),
            ),
        );

        expect($conflict->toArray())->toMatchArray([
            'from_settings_class' => 'FromSettings',
            'from_namespace' => 'from.namespace',
            'from_property' => 'legacyFee',
            'to_settings_class' => 'ToSettings',
            'to_namespace' => 'to.namespace',
            'to_property' => 'deliveryFee',
        ]);
    });
});
