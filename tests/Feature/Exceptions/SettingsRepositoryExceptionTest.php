<?php declare(strict_types=1);

/**
 * Copyright (C) Brian Faust
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

use Cline\Settings\Exceptions\AbstractSettingsRepositoryException;
use Cline\Settings\Exceptions\SettingsExceptionInterface;

describe('settings repository exception', function (): void {
    test('is the base logic exception for repository failures', function (): void {
        $exception = new class('repository failure') extends AbstractSettingsRepositoryException {};

        expect($exception)->toBeInstanceOf(AbstractSettingsRepositoryException::class)
            ->toBeInstanceOf(LogicException::class)
            ->toBeInstanceOf(SettingsExceptionInterface::class)
            ->and($exception->getMessage())->toBe('repository failure');
    });
});
