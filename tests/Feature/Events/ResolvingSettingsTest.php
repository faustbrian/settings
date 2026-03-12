<?php declare(strict_types=1);

/**
 * Copyright (C) Brian Faust
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

use Cline\Settings\Events\ResolvingSettings;
use Cline\Settings\Support\ResolutionChain;
use Cline\Settings\Support\ResolutionTarget;

describe('resolving settings event', function (): void {
    test('stores pre-resolution event payload', function (): void {
        $chain = new ResolutionChain([ResolutionTarget::app()]);
        $event = new ResolvingSettings('SettingsClass', $chain, 'subject');

        expect($event->settingsClass)->toBe('SettingsClass')
            ->and($event->chain)->toBe($chain)
            ->and($event->subject)->toBe('subject');
    });
});
