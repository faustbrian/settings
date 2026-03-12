<?php declare(strict_types=1);

/**
 * Copyright (C) Brian Faust
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

use Cline\Settings\Exceptions\InvalidSettingsPropertyException;
use Cline\Settings\Support\Reference;
use Cline\Settings\Support\ResolutionTarget;
use Cline\Settings\Support\ResolvedSettings;

describe('resolved settings', function (): void {
    test('returns source targets and default usage metadata', function (): void {
        $settings = new readonly class()
        {
            public function __construct(
                public string $apiToken = 'value',
            ) {}
        };

        $target = new ResolutionTarget(
            new Reference('carrier', '123'),
        );
        $resolved = new ResolvedSettings($settings, [
            'apiToken' => $target,
            'enabled' => null,
        ]);

        expect($resolved->sourceFor('apiToken'))->toBe($target)
            ->and($resolved->usesDefault('enabled'))->toBeTrue();
    });

    test('throws when querying an unknown property', function (): void {
        $settings = new readonly class()
        {
            public function __construct(
                public string $apiToken = 'value',
            ) {}
        };

        $resolved = new ResolvedSettings($settings, ['apiToken' => null]);

        expect(fn (): ?ResolutionTarget => $resolved->sourceFor('missing'))
            ->toThrow(InvalidSettingsPropertyException::class);
    });
});
