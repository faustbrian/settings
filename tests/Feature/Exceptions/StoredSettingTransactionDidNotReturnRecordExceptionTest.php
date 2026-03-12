<?php declare(strict_types=1);

/**
 * Copyright (C) Brian Faust
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

use Cline\Settings\Exceptions\StoredSettingTransactionDidNotReturnRecordException;

describe('stored setting transaction did not return record exception', function (): void {
    test('creates the save message', function (): void {
        $exception = StoredSettingTransactionDidNotReturnRecordException::duringSave();

        expect($exception->getMessage())->toBe(
            'Settings repository transaction did not return a stored record.',
        );
    });
});
