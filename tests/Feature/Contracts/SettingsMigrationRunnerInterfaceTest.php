<?php declare(strict_types=1);

/**
 * Copyright (C) Brian Faust
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

use Cline\Settings\Contracts\SettingsMigrationRunnerInterface;

describe('settings migration runner interface', function (): void {
    test('defines run rollback and discovery operations', function (): void {
        $reflection = new ReflectionClass(SettingsMigrationRunnerInterface::class);

        expect($reflection->isInterface())->toBeTrue()
            ->and(array_map(
                static fn (ReflectionMethod $method): string => $method->getName(),
                $reflection->getMethods(),
            ))->toBe([
                'run',
                'rollback',
                'migrationFiles',
            ]);
    });
});
