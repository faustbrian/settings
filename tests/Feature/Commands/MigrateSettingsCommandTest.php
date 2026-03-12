<?php declare(strict_types=1);

/**
 * Copyright (C) Brian Faust
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

use Cline\Settings\Commands\MigrateSettingsCommand;
use Illuminate\Console\Command;
use ReflectionMethod as PhpReflectionMethod;
use ReflectionProperty as PhpReflectionProperty;
use Stringable as PhpStringable;
use Symfony\Component\Console\Input\InputDefinition;
use Symfony\Component\Console\Input\InputInterface;
use Tests\Fixtures\Models\BusinessEntity;
use Tests\Fixtures\Models\Carrier;
use Tests\Fixtures\Models\Organization;
use Tests\Fixtures\Models\ShippingMethod;
use Tests\Fixtures\Models\User;

describe('migrate settings command', function (): void {
    beforeEach(function (): void {
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
    });

    test('prints a no-op message when no settings migrations are pending', function (): void {
        $path = __DIR__.'/../../Fixtures/empty-settings-migrations';

        if (!is_dir($path)) {
            mkdir($path, 0o777, true);
        }

        config()->set('settings.migrations.paths', [$path]);

        $this->artisan('settings:migrate')
            ->expectsOutputToContain('Nothing to migrate.')
            ->assertSuccessful();
    });

    test('runs pending migrations and prints each migrated filename', function (): void {
        config()->set('settings.migrations.paths', [
            __DIR__.'/../../Fixtures/settings-migrations',
        ]);

        $this->artisan('settings:migrate')
            ->expectsOutputToContain('2026_03_11_000000_seed_complex_settings')
            ->expectsOutputToContain('Settings migrations complete.')
            ->assertSuccessful();
    });

    test('filters explicit path options down to non-empty strings', function (): void {
        $path = __DIR__.'/../../Fixtures/settings-migrations';
        $emptyPath = __DIR__.'/../../Fixtures/empty-settings-migrations';

        if (!is_dir($emptyPath)) {
            mkdir($emptyPath, 0o777, true);
        }

        $command = resolve(MigrateSettingsCommand::class);
        migrateCommandInput($command, ['', $path, 123, $emptyPath]);

        expect(migrateCommandPaths($command))->toBe([$path, $emptyPath]);
    });

    test('falls back to an empty path list when the option payload is not an array', function (): void {
        $command = resolve(MigrateSettingsCommand::class);
        migrateCommandInput($command, 'not-an-array');

        expect(migrateCommandPaths($command))->toBe([]);
    });
});

/**
 * @return array<int, string>
 */
function migrateCommandPaths(MigrateSettingsCommand $command): array
{
    $method = new PhpReflectionMethod(MigrateSettingsCommand::class, 'paths');
    $paths = $method->invoke($command);

    assert(is_array($paths));

    return $paths;
}

function migrateCommandInput(MigrateSettingsCommand $command, mixed $value): void
{
    $input = new readonly class($value) implements InputInterface, PhpStringable
    {
        public function __construct(
            private mixed $value,
        ) {}

        public function __toString(): string
        {
            return '';
        }

        public function getFirstArgument(): ?string
        {
            return null;
        }

        public function hasParameterOption(string|array $values, bool $onlyParams = false): bool
        {
            return false;
        }

        public function getParameterOption(
            string|array $values,
            string|bool|int|float|array|null $default = false,
            bool $onlyParams = false,
        ): string|bool|int|float|array|null {
            return $default;
        }

        public function bind(InputDefinition $definition): void {}

        public function validate(): void {}

        public function getArguments(): array
        {
            return [];
        }

        public function getArgument(string $name): mixed
        {
            return null;
        }

        public function setArgument(string $name, mixed $value): void {}

        public function hasArgument(string $name): bool
        {
            return false;
        }

        public function setArguments(array $arguments = []): void {}

        public function getOptions(): array
        {
            return ['path' => $this->value];
        }

        public function getOption(string $name): mixed
        {
            return $name === 'path' ? $this->value : null;
        }

        public function setOption(string $name, mixed $value): void {}

        public function hasOption(string $name): bool
        {
            return $name === 'path';
        }

        public function setInteractive(bool $interactive): void {}

        public function isInteractive(): bool
        {
            return false;
        }
    };

    $property = new PhpReflectionProperty(Command::class, 'input');
    $property->setValue($command, $input);
}
