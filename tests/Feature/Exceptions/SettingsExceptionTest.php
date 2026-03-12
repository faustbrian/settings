<?php declare(strict_types=1);

/**
 * Copyright (C) Brian Faust
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

use Cline\Settings\Exceptions\SettingsException;

describe('settings exception', function (): void {
    test('is a throwable marker interface', function (): void {
        $reflection = new ReflectionClass(SettingsException::class);

        expect($reflection->isInterface())->toBeTrue()
            ->and($reflection->implementsInterface(Throwable::class))->toBeTrue();
    });
});
