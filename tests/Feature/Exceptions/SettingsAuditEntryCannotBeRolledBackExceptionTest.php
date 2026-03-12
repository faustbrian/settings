<?php declare(strict_types=1);

/**
 * Copyright (C) Brian Faust
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

use Cline\Settings\Exceptions\SettingsAuditEntryCannotBeRolledBackException;

describe('settings audit entry cannot be rolled back exception', function (): void {
    test('creates the missing value rollback message', function (): void {
        $exception = SettingsAuditEntryCannotBeRolledBackException::becauseValueIsMissing();

        expect($exception->getMessage())->toBe('The audit entry does not contain a value that can be rolled back.');
    });
});
