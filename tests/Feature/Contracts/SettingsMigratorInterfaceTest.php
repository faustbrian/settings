<?php declare(strict_types=1);

/**
 * Copyright (C) Brian Faust
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

use Cline\Settings\Contracts\SettingsMigratorInterface;

describe('settings migrator interface', function (): void {
    test('defines property and schema mutation operations', function (): void {
        $reflection = new ReflectionClass(SettingsMigratorInterface::class);

        expect($reflection->isInterface())->toBeTrue()
            ->and(array_map(
                static fn (ReflectionMethod $method): string => $method->getName(),
                $reflection->getMethods(),
            ))->toBe([
                'for',
                'add',
                'set',
                'update',
                'delete',
                'deleteIfExists',
                'renameAtTarget',
                'exists',
                'rename',
                'renameSettings',
                'renameProperty',
            ]);
    });
});
