<?php declare(strict_types=1);

/**
 * Copyright (C) Brian Faust
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

use Cline\Settings\Support\SettingsDefinitionResolver;
use Tests\Fixtures\Settings\CarrierCredentialSettings;

describe('settings definition resolver', function (): void {
    test('memoizes definitions by settings class', function (): void {
        $resolver = new SettingsDefinitionResolver();

        $first = $resolver->resolve(CarrierCredentialSettings::class);
        $second = $resolver->resolve(CarrierCredentialSettings::class);

        expect($first)->toBe($second);
    });
});
