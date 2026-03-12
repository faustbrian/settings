<?php declare(strict_types=1);

/**
 * Copyright (C) Brian Faust
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

use Cline\Settings\Exceptions\SettingsSnapshotEncodingException;

describe('settings snapshot encoding exception', function (): void {
    test('wraps the underlying json exception', function (): void {
        $previous = new JsonException('Syntax error', 4);
        $exception = SettingsSnapshotEncodingException::fromJsonException($previous);

        expect($exception->getMessage())->toBe('Syntax error')
            ->and($exception->getCode())->toBe(4)
            ->and($exception->getPrevious())->toBe($previous);
    });
});
