<?php declare(strict_types=1);

/**
 * Copyright (C) Brian Faust
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

use Cline\Settings\Exceptions\SettingsSnapshotEncodingException;
use Cline\Settings\Support\ResolutionTarget;
use Cline\Settings\Support\SettingsSnapshot;
use Cline\Settings\Support\SettingsSnapshotEntry;
use Tests\Fixtures\Settings\CarrierCredentialSettings;

describe('settings snapshot', function (): void {
    test('throws a custom exception when a snapshot cannot be encoded as json', function (): void {
        $snapshot = new SettingsSnapshot([
            new SettingsSnapshotEntry(
                CarrierCredentialSettings::class,
                CarrierCredentialSettings::namespace(),
                'apiToken',
                "\xB1\x31",
                ResolutionTarget::app(),
                1,
            ),
        ]);

        expect(fn (): mixed => $snapshot->toJson())
            ->toThrow(SettingsSnapshotEncodingException::class);
    });
});
