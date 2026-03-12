<?php declare(strict_types=1);

/**
 * Copyright (C) Brian Faust
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

use Cline\Settings\Contracts\SettingsMigrationRepositoryInterface;

describe('settings migration repository interface', function (): void {
    test('defines migration tracking operations', function (): void {
        $reflection = new ReflectionClass(SettingsMigrationRepositoryInterface::class);

        expect($reflection->isInterface())->toBeTrue()
            ->and(array_map(
                static fn (ReflectionMethod $method): string => $method->getName(),
                $reflection->getMethods(),
            ))->toBe([
                'ran',
                'log',
                'delete',
                'nextBatchNumber',
                'last',
            ]);
    });
});
