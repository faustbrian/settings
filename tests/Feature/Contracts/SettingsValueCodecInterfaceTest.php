<?php declare(strict_types=1);

/**
 * Copyright (C) Brian Faust
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

use Cline\Settings\Contracts\SettingsValueCodecInterface;

describe('settings value codec interface', function (): void {
    test('defines encoding decoding and record projection operations', function (): void {
        $reflection = new ReflectionClass(SettingsValueCodecInterface::class);

        expect($reflection->isInterface())->toBeTrue()
            ->and(array_map(
                static fn (ReflectionMethod $method): string => $method->getName(),
                $reflection->getMethods(),
            ))->toBe([
                'encode',
                'decode',
                'toStoredValue',
            ]);
    });
});
