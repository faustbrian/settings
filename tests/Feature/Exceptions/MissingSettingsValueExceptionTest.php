<?php declare(strict_types=1);

/**
 * Copyright (C) Brian Faust
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

use Cline\Settings\Exceptions\MissingSettingsValueException;

describe('missing settings value exception', function (): void {
    test('creates the unresolved property message', function (): void {
        $exception = MissingSettingsValueException::forProperty('SettingsClass', 'apiToken');

        expect($exception->getMessage())->toBe('Missing required value for [SettingsClass::apiToken].');
    });
});
