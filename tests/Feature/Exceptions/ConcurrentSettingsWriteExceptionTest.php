<?php declare(strict_types=1);

/**
 * Copyright (C) Brian Faust
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

use Cline\Settings\Exceptions\ConcurrentSettingsWriteException;

describe('concurrent settings write exception', function (): void {
    test('creates the optimistic lock failure message', function (): void {
        $exception = ConcurrentSettingsWriteException::forProperty(
            'SettingsClass',
            'apiToken',
            1,
            2,
        );

        expect($exception->getMessage())->toContain('SettingsClass::apiToken')
            ->toContain('Expected version [1]')
            ->toContain('found [2]');
    });
});
