<?php declare(strict_types=1);

/**
 * Copyright (C) Brian Faust
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

use Cline\Settings\Exceptions\SettingsAuditEntryMustBePropertyScopedException;

describe('settings audit entry must be property scoped exception', function (): void {
    test('creates the property scope requirement message', function (): void {
        $exception = SettingsAuditEntryMustBePropertyScopedException::forReplayOrRollback();

        expect($exception->getMessage())->toBe('Replay and rollback require a property-scoped audit row.');
    });
});
