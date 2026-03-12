<?php declare(strict_types=1);

/**
 * Copyright (C) Brian Faust
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

use Carbon\CarbonImmutable;
use Cline\Settings\Exceptions\InvalidSettingsPropertyException;
use Cline\Settings\Exceptions\MissingSettingsValueException;
use Cline\Settings\Support\ResolutionChain;
use Cline\Settings\Support\ResolutionTarget;
use Cline\Settings\Support\SettingsDefinition;
use Cline\Struct\Metadata\LazyCast;
use Tests\Fixtures\Settings\CarrierCredentialSettings;

describe('settings definition', function (): void {
    test('resolves namespace defaults encryption and casts from the settings class', function (): void {
        $definition = new SettingsDefinition(CarrierCredentialSettings::class);

        expect($definition->namespace())->toBe('carrier.credentials')
            ->and($definition->hasProperty('apiToken'))->toBeTrue()
            ->and($definition->isEncrypted('apiToken'))->toBeTrue()
            ->and($definition->castFor('rotatesAt'))->toBeInstanceOf(LazyCast::class)
            ->and($definition->defaults(null, new ResolutionChain([ResolutionTarget::app()])))
            ->toMatchArray(['apiToken' => '', 'enabled' => true]);
    });

    test('hydrates and extracts typed settings objects', function (): void {
        $definition = new SettingsDefinition(CarrierCredentialSettings::class);

        $settings = $definition->hydrate([
            'apiToken' => 'secret',
            'rotatesAt' => CarbonImmutable::parse('2026-03-12T00:00:00+00:00'),
            'enabled' => false,
        ]);

        expect($settings)->toBeInstanceOf(CarrierCredentialSettings::class)
            ->and($definition->extract($settings))->toMatchArray([
                'apiToken' => 'secret',
                'enabled' => false,
            ]);
    });

    test('throws for unknown or missing properties', function (): void {
        $definition = new SettingsDefinition(CarrierCredentialSettings::class);

        expect(function () use ($definition): void {
            $definition->ensurePropertyExists('missing');
        })
            ->toThrow(InvalidSettingsPropertyException::class)
            ->and(function () use ($definition): void {
                $definition->hydrate(['apiToken' => 'secret']);
            })
            ->toThrow(MissingSettingsValueException::class);
    });
});
