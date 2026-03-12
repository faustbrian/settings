<?php declare(strict_types=1);

/**
 * Copyright (C) Brian Faust
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Cline\Settings;

use Cline\Morphism\MorphKeyRegistry;
use Cline\Settings\Commands\AuditSettingsCommand;
use Cline\Settings\Commands\DiffSettingsCommand;
use Cline\Settings\Commands\ExportSettingsCommand;
use Cline\Settings\Commands\ImportSettingsCommand;
use Cline\Settings\Commands\InspectConflictsCommand;
use Cline\Settings\Commands\ListSettingsCommand;
use Cline\Settings\Commands\MakeSettingsMigrationCommand;
use Cline\Settings\Commands\MigrateSettingsCommand;
use Cline\Settings\Commands\PruneSettingsCommand;
use Cline\Settings\Commands\ReplaySettingsCommand;
use Cline\Settings\Commands\ResolveSettingsCommand;
use Cline\Settings\Commands\RollbackSettingsCommand;
use Cline\Settings\Commands\RollbackSettingsMigrationsCommand;
use Cline\Settings\Contracts\SettingsAuditLoggerInterface;
use Cline\Settings\Contracts\SettingsDefinitionResolverInterface;
use Cline\Settings\Contracts\SettingsManagerInterface;
use Cline\Settings\Contracts\SettingsMigrationRepositoryInterface;
use Cline\Settings\Contracts\SettingsMigrationRunnerInterface;
use Cline\Settings\Contracts\SettingsMigratorInterface;
use Cline\Settings\Contracts\SettingsRepositoryInterface;
use Cline\Settings\Contracts\SettingsValueCodecInterface;
use Cline\Settings\Database\DatabaseSettingsRepository;
use Cline\Settings\Database\Models\SettingAudit;
use Cline\Settings\Database\Models\StoredSetting;
use Cline\Settings\Exceptions\InvalidConfigurationException;
use Cline\Settings\Migrations\SettingsMigrationRepository;
use Cline\Settings\Migrations\SettingsMigrationRunner;
use Cline\Settings\Migrations\SettingsMigrator;
use Cline\Settings\Support\SettingsAuditLogger;
use Cline\Settings\Support\SettingsDefinitionResolver;
use Cline\Settings\Support\SettingsValueCodec;
use Cline\VariableKeys\Enums\PrimaryKeyType;
use Cline\VariableKeys\Facades\VariableKeys;
use Illuminate\Database\Eloquent\Model;
use Override;
use Spatie\LaravelPackageTools\Package;
use Spatie\LaravelPackageTools\PackageServiceProvider;

use function config;
use function is_array;

/**
 * Laravel service provider for the settings package.
 *
 * This provider is the composition root for the package. It publishes the
 * package assets, registers the singleton collaborators that make up the
 * resolution and persistence pipeline, and applies the morph-map strategy used
 * to serialize {@see Support\Reference} values into storage.
 *
 * The package is intentionally wired around a single
 * {@see SettingsManagerInterface} instance backed by a
 * {@see SettingsRepositoryInterface} implementation. Consumers
 * resolve typed settings through the manager, while repositories, codecs, audit
 * logging, and definition metadata remain replaceable through the container.
 *
 * No resolution policy lives here. Precedence rules, serialization semantics,
 * and write-side invariants remain in the manager and repository layers; this
 * provider is responsible only for composition and boot-time framework wiring.
 *
 * @author Brian Faust <brian@cline.sh>
 */
final class SettingsServiceProvider extends PackageServiceProvider
{
    /**
     * Describe the package assets that should be published and loaded.
     *
     * Registers the configuration file, the initial settings tables migration,
     * and the console entry points for inspecting, importing, exporting,
     * pruning, diffing, and resolving settings snapshots. This method does not
     * perform container registration; it only declares package metadata for the
     * package-tools boot sequence.
     */
    public function configurePackage(Package $package): void
    {
        $package
            ->name('settings')
            ->hasConfigFile()
            ->hasMigration('create_settings_tables')
            ->hasCommands([
                DiffSettingsCommand::class,
                ExportSettingsCommand::class,
                ImportSettingsCommand::class,
                InspectConflictsCommand::class,
                ListSettingsCommand::class,
                MakeSettingsMigrationCommand::class,
                MigrateSettingsCommand::class,
                PruneSettingsCommand::class,
                AuditSettingsCommand::class,
                ReplaySettingsCommand::class,
                ResolveSettingsCommand::class,
                RollbackSettingsMigrationsCommand::class,
                RollbackSettingsCommand::class,
            ]);
    }

    /**
     * Register the package singletons before the package boots.
     *
     * The settings package is designed so definition discovery, value encoding,
     * auditing, and repository access are shared across the request lifecycle.
     * Registering them as singletons keeps resolution behavior consistent and
     * ensures repeated settings lookups reuse memoized definition metadata.
     *
     * The repository binding is the primary extension point for alternative
     * persistence backends. Everything else in the package depends on the
     * contract rather than the database implementation directly. The manager is
     * registered last so container resolution sees the final repository,
     * codec, and audit collaborators that will govern all runtime operations.
     */
    #[Override()]
    public function registeringPackage(): void
    {
        $this->app->singleton(SettingsMigrationRepositoryInterface::class, SettingsMigrationRepository::class);
        $this->app->singleton(SettingsMigrationRunnerInterface::class, SettingsMigrationRunner::class);
        $this->app->singleton(SettingsMigratorInterface::class, SettingsMigrator::class);
        $this->app->singleton(SettingsAuditLoggerInterface::class, SettingsAuditLogger::class);
        $this->app->singleton(SettingsDefinitionResolverInterface::class, SettingsDefinitionResolver::class);
        $this->app->singleton(SettingsRepositoryInterface::class, DatabaseSettingsRepository::class);
        $this->app->singleton(SettingsValueCodecInterface::class, SettingsValueCodec::class);
        $this->app->singleton(SettingsManagerInterface::class, SettingsManager::class);
    }

    /**
     * Apply the configured morph map strategy for polymorphic references.
     *
     * Stored settings rows persist owner and boundary references as type/id
     * pairs. This boot hook ensures those type strings remain stable by
     * applying the configured morph aliases before any settings are read or
     * written. When `settings.enforceMorphKeyMap` is configured the registry
     * will reject unmapped models; otherwise the map is registered as a
     * convenience without making it a hard runtime invariant.
     *
     * This distinction matters for packages that persist settings for
     * polymorphic Eloquent models. Enforced maps turn unresolved model types
     * into an immediate boot-time contract failure instead of allowing drift in
     * the strings stored for owner and boundary references.
     */
    #[Override()]
    public function bootingPackage(): void
    {
        $this->registerVariableKeys();

        /** @var MorphKeyRegistry $registry */
        $registry = $this->app->make(MorphKeyRegistry::class);

        $morphKeyMap = config('settings.morphKeyMap', []);
        $enforceMorphKeyMap = config('settings.enforceMorphKeyMap', []);

        if (!is_array($morphKeyMap)) {
            $morphKeyMap = [];
        }

        if (!is_array($enforceMorphKeyMap)) {
            $enforceMorphKeyMap = [];
        }

        if ($morphKeyMap !== [] && $enforceMorphKeyMap !== []) {
            throw InvalidConfigurationException::conflictingMorphKeyMaps();
        }

        if ($enforceMorphKeyMap !== []) {
            /** @var array<class-string<Model>, string> $enforceMorphKeyMap */
            $registry->enforce($enforceMorphKeyMap);

            return;
        }

        if ($morphKeyMap === []) {
            return;
        }

        /** @var array<class-string<Model>, string> $morphKeyMap */
        $registry->map($morphKeyMap);
    }

    private function registerVariableKeys(): void
    {
        /** @var int|string $configValue */
        $configValue = config('settings.primary_key_type', 'id');
        $primaryKeyType = PrimaryKeyType::tryFrom($configValue) ?? PrimaryKeyType::ID;

        VariableKeys::map([
            StoredSetting::class => [
                'primary_key_type' => $primaryKeyType,
            ],
            SettingAudit::class => [
                'primary_key_type' => $primaryKeyType,
            ],
        ]);
    }
}
