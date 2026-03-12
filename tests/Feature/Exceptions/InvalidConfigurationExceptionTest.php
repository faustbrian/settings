<?php declare(strict_types=1);

/**
 * Copyright (C) Brian Faust
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

use Cline\Settings\Exceptions\InvalidConfigurationException;

describe('invalid configuration exception', function (): void {
    test('creates the conflicting morph key maps message', function (): void {
        $exception = InvalidConfigurationException::conflictingMorphKeyMaps();

        expect($exception)->toBeInstanceOf(InvalidConfigurationException::class)
            ->and($exception->getMessage())->toContain('morphKeyMap')
            ->toContain('enforceMorphKeyMap');
    });
});
