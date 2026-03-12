<?php declare(strict_types=1);

/**
 * Copyright (C) Brian Faust
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

use Cline\Settings\Support\Reference;
use Cline\Settings\Support\ResolutionTarget;

describe('resolution target', function (): void {
    test('normalizes owner and boundary values for persistence', function (): void {
        $target = new ResolutionTarget(
            new Reference('carrier', '123'),
            new Reference('user', '456'),
        );

        expect($target->ownerType())->toBe('carrier')
            ->and($target->ownerId())->toBe('123')
            ->and($target->boundaryType())->toBe('user')
            ->and($target->boundaryId())->toBe('456');
    });

    test('represents app scope with empty persistence values', function (): void {
        $target = ResolutionTarget::app();

        expect($target->ownerType())->toBe('')
            ->and($target->ownerId())->toBe('')
            ->and($target->boundaryType())->toBe('')
            ->and($target->boundaryId())->toBe('');
    });
});
