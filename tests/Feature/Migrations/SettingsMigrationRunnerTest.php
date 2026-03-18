<?php declare(strict_types=1);

/**
 * Copyright (C) Brian Faust
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

use Cline\Settings\Contracts\SettingsMigrationRepositoryInterface;
use Cline\Settings\Contracts\SettingsMigrationRunnerInterface;
use Cline\Settings\Exceptions\SettingsMigrationFileDidNotReturnMigrationException;
use Cline\Settings\Facades\Settings;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\File;
use Tests\Fixtures\Models\BusinessEntity;
use Tests\Fixtures\Models\Carrier;
use Tests\Fixtures\Models\Organization;
use Tests\Fixtures\Models\ShippingMethod;
use Tests\Fixtures\Models\User;
use Tests\Fixtures\Settings\OrganizationMemberSettings;
use Tests\Fixtures\Settings\ShipmentPricingSettings;

describe('settings migration runner', function (): void {
    test('runs tracked settings migrations with complex scoped writes', function (): void {
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

        config()->set('settings.migrations.paths', [
            __DIR__.'/../../Fixtures/settings-migrations',
        ]);

        $ran = resolve(SettingsMigrationRunnerInterface::class)->run();

        $shippingMethod = ShippingMethod::query()->where('name', 'Express')->firstOrFail();
        $businessEntity = BusinessEntity::query()->where('name', 'Finland')->firstOrFail();
        $carrier = Carrier::query()->where('name', 'DHL')->firstOrFail();
        $organization = Organization::query()->where('name', 'Acme Logistics')->firstOrFail();
        $subject = User::query()->where('name', 'Alice')->firstOrFail();

        $shipmentPricing = Settings::for($subject)
            ->ownedBy($shippingMethod)
            ->fallbackTo($carrier)
            ->fallbackTo($businessEntity)
            ->fallbackToApp()
            ->get(ShipmentPricingSettings::class);

        $organizationMember = Settings::for($subject)
            ->ownedBy($organization, $subject)
            ->fallbackTo($organization)
            ->fallbackToApp()
            ->get(OrganizationMemberSettings::class);

        expect($ran)->toBe(['2026_03_11_000000_seed_complex_settings'])
            ->and(DB::table('settings_values')->count())->toBe(10)
            ->and($shipmentPricing->callBeforeDeliveryFee)->toBe(8.5)
            ->and($shipmentPricing->returnFreightDocFee)->toBe(1.2)
            ->and($organizationMember->canUsePrioritySupport)->toBeTrue()
            ->and($organizationMember->dailyShipmentLimit)->toBe(100)
            ->and(DB::table('settings_migrations')->count())->toBe(1);
    });

    test('rolls back tracked complex settings migrations', function (): void {
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

        config()->set('settings.migrations.paths', [
            __DIR__.'/../../Fixtures/settings-migrations',
        ]);

        $runner = resolve(SettingsMigrationRunnerInterface::class);
        $ran = $runner->run();
        $rolledBack = $runner->rollback();

        $shippingMethod = ShippingMethod::query()->where('name', 'Express')->firstOrFail();
        $businessEntity = BusinessEntity::query()->where('name', 'Finland')->firstOrFail();
        $carrier = Carrier::query()->where('name', 'DHL')->firstOrFail();
        $subject = User::query()->where('name', 'Alice')->firstOrFail();

        $shipmentPricing = Settings::for($subject)
            ->ownedBy($shippingMethod)
            ->fallbackTo($carrier)
            ->fallbackTo($businessEntity)
            ->fallbackToApp()
            ->get(ShipmentPricingSettings::class);

        expect($ran)->toBe(['2026_03_11_000000_seed_complex_settings'])
            ->and($rolledBack)->toBe(['2026_03_11_000000_seed_complex_settings'])
            ->and($shipmentPricing->callBeforeDeliveryFee)->toBe(0.0)
            ->and($shipmentPricing->returnFreightDocFee)->toBe(0.1)
            ->and(DB::table('settings_migrations')->count())->toBe(0);
    });

    test('runs pending migrations from explicit paths and tracks filenames without the php suffix', function (): void {
        $path = tempSettingsMigrationPath('runner-explicit');

        writeSettingsMigration($path, '2026_03_11_000000_seed_app_settings', <<<'PHP'
<?php declare(strict_types=1);

use Cline\Settings\Migrations\AbstractSettingsMigration;
use Tests\Fixtures\Settings\ShipmentPricingSettings;

return new class() extends AbstractSettingsMigration
{
    public function up(): void
    {
        $this->migrator->for(ShipmentPricingSettings::class)
            ->app()
            ->add('callBeforeDeliveryFee', 4.2);
    }

    public function down(): void
    {
        $this->migrator->for(ShipmentPricingSettings::class)
            ->app()
            ->deleteIfExists('callBeforeDeliveryFee');
    }
};
PHP);

        $runner = resolve(SettingsMigrationRunnerInterface::class);
        $repository = resolve(SettingsMigrationRepositoryInterface::class);

        expect($runner->migrationFiles([$path]))->toBe([
            '2026_03_11_000000_seed_app_settings' => $path.'/2026_03_11_000000_seed_app_settings.php',
        ]);

        $ran = $runner->run([$path]);
        $rerun = $runner->run([$path]);

        expect($ran)->toBe(['2026_03_11_000000_seed_app_settings'])
            ->and($rerun)->toBe([])
            ->and($repository->ran())->toBe(['2026_03_11_000000_seed_app_settings'])
            ->and(Settings::for(null)->fallbackToApp()->value(
                ShipmentPricingSettings::class,
                'callBeforeDeliveryFee',
            ))->toBe(4.2);

        File::deleteDirectory($path);
    });

    test('uses configured paths and ignores invalid configured path values', function (): void {
        $path = tempSettingsMigrationPath('runner-configured');

        writeSettingsMigration($path, '2026_03_11_000001_seed_configured_settings', <<<'PHP'
<?php declare(strict_types=1);

use Cline\Settings\Migrations\AbstractSettingsMigration;
use Tests\Fixtures\Settings\ShipmentPricingSettings;

return new class() extends AbstractSettingsMigration
{
    public function up(): void
    {
        $this->migrator->for(ShipmentPricingSettings::class)
            ->app()
            ->set('returnFreightDocFee', 0.9);
    }

    public function down(): void
    {
        $this->migrator->for(ShipmentPricingSettings::class)
            ->app()
            ->deleteIfExists('returnFreightDocFee');
    }
};
PHP);

        config()->set('settings.migrations.paths', [$path, '', null, 123]);

        $runner = resolve(SettingsMigrationRunnerInterface::class);
        $ran = $runner->run();

        expect($ran)->toBe(['2026_03_11_000001_seed_configured_settings'])
            ->and(Settings::for(null)->fallbackToApp()->value(
                ShipmentPricingSettings::class,
                'returnFreightDocFee',
            ))->toBe(0.9);

        config()->set('settings.migrations.paths', 'invalid');

        expect($runner->migrationFiles())->toBe([])
            ->and($runner->run())->toBe([])
            ->and($runner->rollback())->toBe([]);

        File::deleteDirectory($path);
    });

    test('rolls back tracked migrations and skips missing files during rollback', function (): void {
        $path = tempSettingsMigrationPath('runner-rollback');

        writeSettingsMigration($path, '2026_03_11_000002_seed_rollback_settings', <<<'PHP'
<?php declare(strict_types=1);

use Cline\Settings\Migrations\AbstractSettingsMigration;
use Tests\Fixtures\Settings\ShipmentPricingSettings;

return new class() extends AbstractSettingsMigration
{
    public function up(): void
    {
        $this->migrator->for(ShipmentPricingSettings::class)
            ->app()
            ->set('callBeforeDeliveryFee', 7.7);
    }

    public function down(): void
    {
        $this->migrator->for(ShipmentPricingSettings::class)
            ->app()
            ->deleteIfExists('callBeforeDeliveryFee');
    }
};
PHP);

        $runner = resolve(SettingsMigrationRunnerInterface::class);
        $repository = resolve(SettingsMigrationRepositoryInterface::class);

        expect($runner->run([$path]))->toBe(['2026_03_11_000002_seed_rollback_settings']);
        expect($runner->rollback(1, [$path]))->toBe(['2026_03_11_000002_seed_rollback_settings'])
            ->and($repository->ran())->toBe([])
            ->and(Settings::for(null)->fallbackToApp()->value(
                ShipmentPricingSettings::class,
                'callBeforeDeliveryFee',
                'missing',
            ))->toBe('missing');

        expect($runner->run([$path]))->toBe(['2026_03_11_000002_seed_rollback_settings']);

        File::delete($path.'/2026_03_11_000002_seed_rollback_settings.php');

        expect($runner->rollback(1, [$path]))->toBe([])
            ->and($repository->ran())->toBe(['2026_03_11_000002_seed_rollback_settings']);

        File::deleteDirectory($path);
    });

    test('throws when a migration file does not return a settings migration instance', function (): void {
        $path = tempSettingsMigrationPath('runner-invalid');

        File::put($path.'/2026_03_11_000003_invalid_migration.php', <<<'PHP'
<?php declare(strict_types=1);

return ['not-a-migration'];
PHP);

        $runner = resolve(SettingsMigrationRunnerInterface::class);

        expect(fn () => $runner->run([$path]))
            ->toThrow(SettingsMigrationFileDidNotReturnMigrationException::class);

        File::deleteDirectory($path);
    });
});

function tempSettingsMigrationPath(string $suffix): string
{
    $path = __DIR__.'/../../Fixtures/tmp-'.$suffix.'-'.bin2hex(random_bytes(4));

    File::deleteDirectory($path);
    File::ensureDirectoryExists($path);

    return $path;
}

function writeSettingsMigration(string $path, string $name, string $contents): void
{
    File::put($path.'/'.$name.'.php', $contents);
}
