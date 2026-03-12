<?php declare(strict_types=1);

/**
 * Copyright (C) Brian Faust
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

use Cline\Settings\Exceptions\SettingsMigrationFileDidNotReturnMigrationException;

describe('settings migration file did not return migration exception', function (): void {
    test('creates the invalid migration file message', function (): void {
        $exception = SettingsMigrationFileDidNotReturnMigrationException::fromPath('/tmp/migration.php');

        expect($exception->getMessage())->toContain('/tmp/migration.php')
            ->toContain('did not return a SettingsMigration instance');
    });
});
