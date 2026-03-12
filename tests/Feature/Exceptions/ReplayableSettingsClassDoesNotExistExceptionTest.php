<?php declare(strict_types=1);

/**
 * Copyright (C) Brian Faust
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

use Cline\Settings\Exceptions\ReplayableSettingsClassDoesNotExistException;

describe('replayable settings class does not exist exception', function (): void {
    test('creates the replayability message', function (): void {
        $exception = ReplayableSettingsClassDoesNotExistException::forClass('MissingClass');

        expect($exception->getMessage())->toContain('MissingClass')
            ->toContain('does not exist');
    });
});
