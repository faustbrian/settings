<?php declare(strict_types=1);

/**
 * Copyright (C) Brian Faust
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

use Cline\Settings\Contracts\SettingsRepositoryInterface;

describe('settings repository interface', function (): void {
    test('defines the persistence contract', function (): void {
        $reflection = new ReflectionClass(SettingsRepositoryInterface::class);

        expect($reflection->isInterface())->toBeTrue()
            ->and(array_map(
                static fn (ReflectionMethod $method): string => $method->getName(),
                $reflection->getMethods(),
            ))->toBe([
                'find',
                'save',
                'delete',
                'purge',
                'all',
                'prune',
            ]);
    });
});
