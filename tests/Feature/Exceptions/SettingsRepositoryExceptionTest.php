<?php declare(strict_types=1);

/**
 * Copyright (C) Brian Faust
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

use Cline\Settings\Exceptions\SettingsException;
use Cline\Settings\Exceptions\SettingsRepositoryException;

describe('settings repository exception', function (): void {
    test('is the base logic exception for repository failures', function (): void {
        $exception = new class('repository failure') extends SettingsRepositoryException {};

        expect($exception)->toBeInstanceOf(SettingsRepositoryException::class)
            ->toBeInstanceOf(LogicException::class)
            ->toBeInstanceOf(SettingsException::class)
            ->and($exception->getMessage())->toBe('repository failure');
    });
});
