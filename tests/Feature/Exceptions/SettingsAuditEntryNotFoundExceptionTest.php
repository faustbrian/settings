<?php declare(strict_types=1);

/**
 * Copyright (C) Brian Faust
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

use Cline\Settings\Exceptions\SettingsAuditEntryNotFoundException;

describe('settings audit entry not found exception', function (): void {
    test('creates the missing audit row message', function (): void {
        $exception = SettingsAuditEntryNotFoundException::forId(42);

        expect($exception->getMessage())->toBe('The requested audit row [42] does not exist.');
    });
});
