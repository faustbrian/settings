<?php declare(strict_types=1);

/**
 * Copyright (C) Brian Faust
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

use Cline\Settings\Contracts\SettingsManagerInterface;

describe('settings manager interface', function (): void {
    test('defines the public manager API surface', function (): void {
        $reflection = new ReflectionClass(SettingsManagerInterface::class);

        expect($reflection->isInterface())->toBeTrue()
            ->and(array_map(
                static fn (ReflectionMethod $method): string => $method->getName(),
                $reflection->getMethods(),
            ))->toBe([
                'for',
                'resolve',
                'resolveWithMetadata',
                'save',
                'getValue',
                'setValue',
                'compareAndSetValue',
                'forgetValue',
                'forgetSettings',
                'inspect',
                'export',
                'import',
                'rename',
                'audit',
                'inspectRenameConflicts',
                'replay',
                'rollback',
                'prune',
            ]);
    });
});
