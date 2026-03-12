<?php declare(strict_types=1);

/**
 * Copyright (C) Brian Faust
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

use Cline\Settings\Exceptions\SettingsSerializationException;

describe('settings serialization exception', function (): void {
    test('wraps the previous serialization error', function (): void {
        $previous = new RuntimeException('boom');
        $exception = SettingsSerializationException::forProperty('SettingsClass', 'apiToken', $previous);

        expect($exception->getMessage())->toBe('Unable to serialize [SettingsClass::apiToken].')
            ->and($exception->getPrevious())->toBe($previous);
    });
});
