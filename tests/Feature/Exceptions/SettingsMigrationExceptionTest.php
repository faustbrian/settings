<?php declare(strict_types=1);

/**
 * Copyright (C) Brian Faust
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

use Cline\Settings\Exceptions\AbstractSettingsMigrationException;
use Cline\Settings\Exceptions\SettingsExceptionInterface;

describe('settings migration exception', function (): void {
    test('is the base logic exception for migration failures', function (): void {
        $exception = new class('migration failure') extends AbstractSettingsMigrationException {};

        expect($exception)->toBeInstanceOf(AbstractSettingsMigrationException::class)
            ->toBeInstanceOf(LogicException::class)
            ->toBeInstanceOf(SettingsExceptionInterface::class)
            ->and($exception->getMessage())->toBe('migration failure');
    });
});
