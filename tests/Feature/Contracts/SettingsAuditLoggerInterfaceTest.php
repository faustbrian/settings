<?php declare(strict_types=1);

/**
 * Copyright (C) Brian Faust
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

use Cline\Settings\Contracts\SettingsAuditLoggerInterface;
use Cline\Settings\Support\ResolutionTarget;

describe('settings audit logger interface', function (): void {
    test('defines the logging contract', function (): void {
        $reflection = new ReflectionClass(SettingsAuditLoggerInterface::class);
        $method = $reflection->getMethod('log');

        expect($reflection->isInterface())->toBeTrue()
            ->and($method->getParameters())->toHaveCount(8)
            ->and($method->getParameters()[4]->getType()?->getName())->toBe(ResolutionTarget::class)
            ->and($method->hasReturnType())->toBeTrue()
            ->and($method->getReturnType()?->getName())->toBe('void');
    });
});
