<?php declare(strict_types=1);

/**
 * Copyright (C) Brian Faust
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

use Cline\Settings\Commands\InspectConflictsCommand;
use Cline\Settings\Facades\Settings;
use Illuminate\Console\Command;
use Illuminate\Console\OutputStyle;
use Illuminate\Support\Facades\Artisan;
use ReflectionProperty as BaseReflectionProperty;
use Stringable as NativeStringable;
use Symfony\Component\Console\Input\ArrayInput;
use Symfony\Component\Console\Input\InputDefinition;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\BufferedOutput;
use Tests\Fixtures\Models\Carrier;
use Tests\Fixtures\Models\User;
use Tests\Fixtures\Settings\LegacySchemaClassSettings;
use Tests\Fixtures\Settings\RenamedSchemaClassSettings;

describe('inspect conflicts command', function (): void {
    test('fails when source and destination classes are not strings', function (): void {
        $command = resolve(InspectConflictsCommand::class);
        inspectConflictsInput($command, [
            'from-settings' => ['bad'],
            'to-settings' => RenamedSchemaClassSettings::class,
        ]);

        expect($command->handle())->toBe(Command::FAILURE);
    });

    test('fails when either settings class does not exist', function (): void {
        $this->artisan('settings:conflicts', [
            'from-settings' => 'Tests\\Fixtures\\Settings\\MissingA',
            'to-settings' => RenamedSchemaClassSettings::class,
        ])->expectsOutputToContain('Both settings classes must exist.')
            ->assertFailed();
    });

    test('prints conflicts for a full settings class rename', function (): void {
        $carrier = Carrier::query()->create(['name' => 'DHL']);
        $subject = User::query()->create(['name' => 'Alice']);

        Settings::for($subject)->ownedBy($carrier)->save(
            new LegacySchemaClassSettings(
                apiKey: 'legacy-key',
                enabled: true,
            ),
        );

        Settings::for($subject)->ownedBy($carrier)->save(
            new RenamedSchemaClassSettings(
                apiKey: 'existing-target',
                enabled: false,
            ),
        );

        $exitCode = Artisan::call('settings:conflicts', [
            'from-settings' => LegacySchemaClassSettings::class,
            'to-settings' => RenamedSchemaClassSettings::class,
        ]);

        expect($exitCode)->toBe(0)
            ->and(Artisan::output())->toContain('from_settings_class');
    });

    test('prints conflicts for a property rename', function (): void {
        $carrier = Carrier::query()->create(['name' => 'UPS']);
        $subject = User::query()->create(['name' => 'Bob']);

        Settings::for($subject)->ownedBy($carrier)->set(
            LegacySchemaClassSettings::class,
            'apiKey',
            'legacy',
        );

        Settings::for($subject)->ownedBy($carrier)->set(
            LegacySchemaClassSettings::class,
            'enabled',
            false,
        );

        $exitCode = Artisan::call('settings:conflicts', [
            'from-settings' => LegacySchemaClassSettings::class,
            'to-settings' => LegacySchemaClassSettings::class,
            '--from-property' => 'apiKey',
            '--to-property' => 'enabled',
            '--from-namespace' => 'legacy.schema.class',
            '--to-namespace' => 'legacy.schema.class',
        ]);

        expect($exitCode)->toBe(0)
            ->and(Artisan::output())->toContain('from_property');
    });
});

/**
 * @param array<string, mixed> $arguments
 */
function inspectConflictsInput(InspectConflictsCommand $command, array $arguments): void
{
    $input = new readonly class($arguments) implements InputInterface, NativeStringable
    {
        /**
         * @param array<string, mixed> $arguments
         */
        public function __construct(
            private array $arguments,
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
            return $this->arguments;
        }

        public function getArgument(string $name): mixed
        {
            return $this->arguments[$name] ?? null;
        }

        public function setArgument(string $name, mixed $value): void {}

        public function hasArgument(string $name): bool
        {
            return true;
        }

        public function setArguments(array $arguments = []): void {}

        public function getOptions(): array
        {
            return [];
        }

        public function getOption(string $name): mixed
        {
            return null;
        }

        public function setOption(string $name, mixed $value): void {}

        public function hasOption(string $name): bool
        {
            return true;
        }

        public function setInteractive(bool $interactive): void {}

        public function isInteractive(): bool
        {
            return false;
        }
    };

    $property = new BaseReflectionProperty(Command::class, 'input');
    $property->setValue($command, $input);

    $output = new BaseReflectionProperty(Command::class, 'output');
    $output->setValue(
        $command,
        new OutputStyle(
            new ArrayInput([]),
            new BufferedOutput(),
        ),
    );
}
