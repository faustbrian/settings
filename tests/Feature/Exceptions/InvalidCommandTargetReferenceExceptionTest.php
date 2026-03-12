<?php declare(strict_types=1);

/**
 * Copyright (C) Brian Faust
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

use Cline\Settings\Exceptions\InvalidCommandTargetReferenceException;

describe('invalid command target reference exception', function (): void {
    test('creates the null target message', function (): void {
        $exception = InvalidCommandTargetReferenceException::forValue(null);

        expect($exception->getMessage())->toBe(
            'Target reference must use the type:id format.',
        );
    });

    test('creates the invalid target message', function (): void {
        $exception = InvalidCommandTargetReferenceException::forValue('carrier');

        expect($exception->getMessage())->toBe(
            'Target reference [carrier] must use the type:id format.',
        );
    });
});
