<?php declare(strict_types=1);

/**
 * Copyright (C) Brian Faust
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

use Cline\Settings\Commands\ImportSettingsCommand;
use Cline\Settings\Facades\Settings;
use Symfony\Component\Console\Input\ArrayInput;
use Symfony\Component\Console\Output\BufferedOutput;
use Tests\Fixtures\Models\BusinessEntity;
use Tests\Fixtures\Models\User;
use Tests\Fixtures\Settings\ShipmentPricingSettings;

beforeEach(function (): void {
    $this->temporaryFiles = [];
});

afterEach(function (): void {
    foreach ($this->temporaryFiles as $file) {
        if (!is_string($file)) {
            continue;
        }

        if (!file_exists($file)) {
            continue;
        }

        unlink($file);
    }
});

describe('import settings command', function (): void {
    test('imports a valid snapshot file', function (): void {
        $businessEntity = BusinessEntity::query()->create([
            'name' => 'Finland',
            'country_code' => 'FI',
        ]);
        $subject = User::query()->create(['name' => 'Alice']);

        $path = tempnam(sys_get_temp_dir(), 'settings-import-');
        expect($path)->toBeString();
        $this->temporaryFiles[] = $path;

        file_put_contents($path, json_encode([
            'entries' => [
                [
                    'settings_class' => ShipmentPricingSettings::class,
                    'settings_namespace' => 'shipment.pricing',
                    'property' => 'callBeforeDeliveryFee',
                    'value' => 4.75,
                    'owner_type' => 'business_entity',
                    'owner_id' => (string) $businessEntity->getKey(),
                    'boundary_type' => null,
                    'boundary_id' => null,
                    'version' => 1,
                ],
                [
                    'settings_class' => ShipmentPricingSettings::class,
                    'settings_namespace' => 'shipment.pricing',
                    'property' => 'returnFreightDocFee',
                    'value' => 0.45,
                    'owner_type' => 'business_entity',
                    'owner_id' => (string) $businessEntity->getKey(),
                    'boundary_type' => null,
                    'boundary_id' => null,
                    'version' => 1,
                ],
            ],
        ], \JSON_THROW_ON_ERROR));

        $this->artisan('settings:import', ['path' => $path])
            ->expectsOutputToContain('Imported 2 settings rows.')
            ->assertSuccessful();

        $resolved = Settings::for($subject)
            ->ownedBy($businessEntity)
            ->fallbackToApp()
            ->get(ShipmentPricingSettings::class);

        expect($resolved->callBeforeDeliveryFee)->toBe(4.75)
            ->and($resolved->returnFreightDocFee)->toBe(0.45);
    });

    test('fails when the import path is not a string', function (): void {
        $command = new ImportSettingsCommand();
        $command->setLaravel($this->app);

        $output = new BufferedOutput();

        $exitCode = $command->run(
            new ArrayInput([
                'path' => ['not-a-string'],
            ]),
            $output,
        );

        expect($exitCode)->toBe(ImportSettingsCommand::FAILURE)
            ->and($output->fetch())->toContain('The import path must be a string.');
    });

    test('fails when the snapshot file cannot be read', function (): void {
        $path = sys_get_temp_dir().'/missing-settings-snapshot.json';

        set_error_handler(static fn (): bool => true);

        try {
            $this->artisan('settings:import', ['path' => $path])
                ->expectsOutputToContain('Unable to read the snapshot file.')
                ->assertFailed();
        } finally {
            restore_error_handler();
        }
    });

    test('fails when the snapshot file contains invalid json', function (): void {
        $path = tempnam(sys_get_temp_dir(), 'settings-import-');
        expect($path)->toBeString();
        $this->temporaryFiles[] = $path;

        file_put_contents($path, '{invalid json');

        $this->artisan('settings:import', ['path' => $path])
            ->expectsOutputToContain('Syntax error')
            ->assertFailed();
    });

    test('fails when the snapshot payload does not decode to an array', function (): void {
        $path = tempnam(sys_get_temp_dir(), 'settings-import-');
        expect($path)->toBeString();
        $this->temporaryFiles[] = $path;

        file_put_contents($path, '"not-an-array"');

        $this->artisan('settings:import', ['path' => $path])
            ->expectsOutputToContain('The snapshot payload must decode to an array.')
            ->assertFailed();
    });
});
