<?php declare(strict_types=1);

/**
 * Copyright (C) Brian Faust
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

use Cline\Settings\Support\Reference;
use Cline\Settings\Support\ResolutionChain;
use Cline\Settings\Support\ResolutionTarget;

describe('resolution chain', function (): void {
    test('stores ordered resolution targets', function (): void {
        $targets = [
            new ResolutionTarget(
                new Reference('carrier', '1'),
            ),
            ResolutionTarget::app(),
        ];

        $chain = new ResolutionChain($targets);

        expect($chain->targets)->toBe($targets);
    });
});
