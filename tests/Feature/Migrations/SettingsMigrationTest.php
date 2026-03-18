<?php declare(strict_types=1);

/**
 * Copyright (C) Brian Faust
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

use Cline\Settings\Contracts\SettingsMigratorInterface;
use Cline\Settings\Migrations\AbstractSettingsMigration;

describe('settings migration base class', function (): void {
    test('resolves the migrator interface from the container', function (): void {
        $migrator = Mockery::mock(SettingsMigratorInterface::class);
        $this->app->instance(SettingsMigratorInterface::class, $migrator);

        $migration = new class() extends AbstractSettingsMigration
        {
            public function up(): void {}

            public function down(): void {}

            public function migrator(): SettingsMigratorInterface
            {
                return $this->migrator;
            }
        };

        expect($migration->migrator())->toBe($migrator);
    });
});
