<?php declare(strict_types=1);

/**
 * Copyright (C) Brian Faust
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

use Cline\Settings\Settings;
use Cline\Settings\Support\ResolutionChain;
use Cline\Settings\Support\ResolutionTarget;
use Tests\Fixtures\Settings\CarrierCredentialSettings;

describe('settings base class', function (): void {
    test('uses the class name as the default namespace', function (): void {
        $settings = new readonly class('value') extends Settings
        {
            public function __construct(
                public string $apiToken,
            ) {}
        };

        expect($settings::namespace())->toBe($settings::class);
    });

    test('returns explicit defaults for fixture settings classes', function (): void {
        $defaults = CarrierCredentialSettings::defaultsFor(
            null,
            new ResolutionChain([ResolutionTarget::app()]),
        );

        expect($defaults)->toMatchArray([
            'apiToken' => '',
            'enabled' => true,
        ]);
    });
});
