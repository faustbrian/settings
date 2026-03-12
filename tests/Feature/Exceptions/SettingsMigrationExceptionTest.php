<?php declare(strict_types=1);

/**
 * Copyright (C) Brian Faust
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

use Cline\Settings\Exceptions\SettingsException;
use Cline\Settings\Exceptions\SettingsMigrationException;

describe('settings migration exception', function (): void {
    test('is the base logic exception for migration failures', function (): void {
        $exception = new class('migration failure') extends SettingsMigrationException {};

        expect($exception)->toBeInstanceOf(SettingsMigrationException::class)
            ->toBeInstanceOf(LogicException::class)
            ->toBeInstanceOf(SettingsException::class)
            ->and($exception->getMessage())->toBe('migration failure');
    });
});
