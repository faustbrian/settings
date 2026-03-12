<?php declare(strict_types=1);

/**
 * Copyright (C) Brian Faust
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

use Cline\Settings\Support\ResolutionTarget;
use Cline\Settings\Support\SettingsQuery;

describe('settings query', function (): void {
    test('stores immutable settings query filters', function (): void {
        $target = ResolutionTarget::app();
        $query = new SettingsQuery(
            settingsClass: 'SettingsClass',
            namespace: 'settings.namespace',
            property: 'apiToken',
            target: $target,
        );

        expect($query->settingsClass)->toBe('SettingsClass')
            ->and($query->namespace)->toBe('settings.namespace')
            ->and($query->property)->toBe('apiToken')
            ->and($query->target)->toBe($target);
    });
});
