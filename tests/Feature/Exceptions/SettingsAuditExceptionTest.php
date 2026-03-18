<?php declare(strict_types=1);

/**
 * Copyright (C) Brian Faust
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

use Cline\Settings\Exceptions\AbstractSettingsAuditException;
use Cline\Settings\Exceptions\SettingsExceptionInterface;

describe('settings audit exception', function (): void {
    test('is the base invalid argument exception for audit failures', function (): void {
        $exception = new class('audit failure') extends AbstractSettingsAuditException {};

        expect($exception)->toBeInstanceOf(AbstractSettingsAuditException::class)
            ->toBeInstanceOf(InvalidArgumentException::class)
            ->toBeInstanceOf(SettingsExceptionInterface::class)
            ->and($exception->getMessage())->toBe('audit failure');
    });
});
