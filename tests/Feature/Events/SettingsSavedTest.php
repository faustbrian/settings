<?php declare(strict_types=1);

/**
 * Copyright (C) Brian Faust
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

use Cline\Settings\Events\SettingsSaved;
use Cline\Settings\Support\ResolutionTarget;

describe('settings saved event', function (): void {
    test('stores post-save event payload', function (): void {
        $target = ResolutionTarget::app();
        $event = new SettingsSaved('SettingsClass', ['apiToken' => 'secret'], $target, 'subject');

        expect($event->settingsClass)->toBe('SettingsClass')
            ->and($event->values)->toBe(['apiToken' => 'secret'])
            ->and($event->target)->toBe($target)
            ->and($event->subject)->toBe('subject');
    });
});
