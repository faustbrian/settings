<?php declare(strict_types=1);

/**
 * Copyright (C) Brian Faust
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

use Cline\Settings\Contracts\SettingsDefinitionResolverInterface;
use Cline\Settings\Support\SettingsDefinition;

describe('settings definition resolver interface', function (): void {
    test('defines the resolve contract', function (): void {
        $reflection = new ReflectionClass(SettingsDefinitionResolverInterface::class);
        $method = $reflection->getMethod('resolve');

        expect($reflection->isInterface())->toBeTrue()
            ->and($method->getParameters())->toHaveCount(1)
            ->and($method->getParameters()[0]->getType()?->getName())->toBe('string')
            ->and($method->getReturnType()?->getName())->toBe(SettingsDefinition::class);
    });
});
