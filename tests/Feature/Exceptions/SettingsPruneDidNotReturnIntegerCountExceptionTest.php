<?php declare(strict_types=1);

/**
 * Copyright (C) Brian Faust
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

use Cline\Settings\Exceptions\SettingsPruneDidNotReturnIntegerCountException;

describe('settings prune did not return integer count exception', function (): void {
    test('creates the repository message', function (): void {
        $exception = SettingsPruneDidNotReturnIntegerCountException::fromRepository();

        expect($exception->getMessage())->toBe(
            'Settings prune did not return an integer delete count.',
        );
    });
});
