<?php declare(strict_types=1);

/**
 * Copyright (C) Brian Faust
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

use Cline\Settings\Commands\RollbackSettingsMigrationsCommand;
use Cline\Settings\Contracts\SettingsMigrationRunnerInterface;
use Cline\Settings\Migrations\SettingsMigrationRepository;
use Cline\Settings\Migrations\SettingsMigrationRunner;
use Illuminate\Filesystem\Filesystem;
use Illuminate\Support\Facades\DB;
use Symfony\Component\Console\Input\ArrayInput;
use Symfony\Component\Console\Output\BufferedOutput;
use Tests\Fixtures\Models\BusinessEntity;
use Tests\Fixtures\Models\Carrier;
use Tests\Fixtures\Models\Organization;
use Tests\Fixtures\Models\ShippingMethod;
use Tests\Fixtures\Models\User;

describe('rollback settings migrations command', function (): void {
    test('reports when there is nothing to roll back', function (): void {
        config()->set('settings.migrations.paths', [
            __DIR__.'/../../Fixtures/settings-migrations',
        ]);

        $this->artisan('settings:migrate-rollback')
            ->expectsOutput('Nothing to rollback.')
            ->assertSuccessful();
    });

    test('lists rolled back migrations from the configured fixture path', function (): void {
        BusinessEntity::query()->create([
            'name' => 'Finland',
            'country_code' => 'FI',
        ]);
        $carrier = Carrier::query()->create(['name' => 'DHL']);
        ShippingMethod::query()->create([
            'carrier_id' => $carrier->getKey(),
            'name' => 'Express',
        ]);
        Organization::query()->create(['name' => 'Acme Logistics']);
        User::query()->create(['name' => 'Alice']);

        $path = __DIR__.'/../../Fixtures/settings-migrations';

        config()->set('settings.migrations.paths', [$path]);

        resolve(SettingsMigrationRunnerInterface::class)->run();

        $this->artisan('settings:migrate-rollback', ['--path' => [$path]])
            ->expectsOutput('2026_03_11_000000_seed_complex_settings')
            ->expectsOutput('Settings migration rollback complete.')
            ->assertSuccessful();
    });

    test('falls back to one step and filters invalid paths when options are not normalized', function (): void {
        $path = sys_get_temp_dir().'/settings-rollback-default-step';
        $filesystem = new Filesystem();

        $filesystem->deleteDirectory($path);
        $filesystem->ensureDirectoryExists($path);
        $filesystem->put($path.'/2026_03_11_000000_noop.php', noOpMigrationStub());

        $runner = new SettingsMigrationRunner(
            $filesystem,
            new SettingsMigrationRepository($this->app['db']),
        );

        $runner->run([$path]);

        $command = new RollbackSettingsMigrationsCommand($runner);
        $command->setLaravel($this->app);

        $output = new BufferedOutput();

        $command->run(
            new ArrayInput([
                '--step' => 'two',
                '--path' => ['', $path, 123],
            ]),
            $output,
        );

        expect($output->fetch())->toContain('2026_03_11_000000_noop')
            ->and(DB::table('settings_migrations')->count())->toBe(0);

        $filesystem->deleteDirectory($path);
    });

    test('passes through integer step values to the runner', function (): void {
        $path = sys_get_temp_dir().'/settings-rollback-int-step';
        $filesystem = new Filesystem();

        $filesystem->deleteDirectory($path);
        $filesystem->ensureDirectoryExists($path);
        $filesystem->put($path.'/2026_03_11_000000_first.php', noOpMigrationStub());
        $filesystem->put($path.'/2026_03_11_000001_second.php', noOpMigrationStub());

        $runner = new SettingsMigrationRunner(
            $filesystem,
            new SettingsMigrationRepository($this->app['db']),
        );

        $runner->run([$path]);

        $command = new RollbackSettingsMigrationsCommand($runner);
        $command->setLaravel($this->app);

        $output = new BufferedOutput();

        $command->run(
            new ArrayInput([
                '--step' => 2,
                '--path' => [$path],
            ]),
            $output,
        );

        expect($output->fetch())->toContain('2026_03_11_000000_first')
            ->toContain('2026_03_11_000001_second')
            ->and(DB::table('settings_migrations')->count())->toBe(0);

        $filesystem->deleteDirectory($path);
    });
});

function noOpMigrationStub(): string
{
    return <<<'PHP'
<?php declare(strict_types=1);

use Cline\Settings\Migrations\SettingsMigration;

return new class() extends SettingsMigration
{
    public function up(): void
    {
        //
    }

    public function down(): void
    {
        //
    }
};
PHP;
}
