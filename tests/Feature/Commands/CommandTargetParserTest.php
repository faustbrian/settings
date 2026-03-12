<?php declare(strict_types=1);

/**
 * Copyright (C) Brian Faust
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

use Cline\Settings\Commands\CommandTargetParser;
use Cline\Settings\Exceptions\InvalidCommandTargetReferenceException;

describe('command target parser', function (): void {
    test('throws a custom exception when a command target reference is malformed', function (): void {
        expect(fn (): mixed => CommandTargetParser::parse('carrier'))
            ->toThrow(InvalidCommandTargetReferenceException::class);
    });
});
