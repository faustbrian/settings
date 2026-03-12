<?php declare(strict_types=1);

/**
 * Copyright (C) Brian Faust
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

use Cline\Settings\Exceptions\ConcurrentSettingsWriteException;
use Cline\Settings\Exceptions\ReplayableSettingsClassDoesNotExistException;
use Cline\Settings\Exceptions\SettingsAuditEntryCannotBeRolledBackException;
use Cline\Settings\Exceptions\SettingsAuditEntryMustBePropertyScopedException;
use Cline\Settings\Exceptions\SettingsAuditEntryNotFoundException;
use Cline\Settings\Exceptions\SettingsSerializationException;
use Cline\Settings\Facades\Settings;
use Cline\Settings\Support\Reference;
use Cline\Settings\Support\ResolutionTarget;
use Cline\Settings\Support\SettingsAuditQuery;
use Cline\Settings\Support\SettingsQuery;
use Cline\Settings\Support\SettingsRename;
use Illuminate\Support\Facades\DB;
use Tests\Fixtures\Models\BusinessEntity;
use Tests\Fixtures\Models\Carrier;
use Tests\Fixtures\Models\User;
use Tests\Fixtures\Settings\AtomicSaveFailureSettings;
use Tests\Fixtures\Settings\CarrierCredentialSettings;
use Tests\Fixtures\Settings\LegacySchemaClassSettings;
use Tests\Fixtures\Settings\RenamedSchemaClassSettings;
use Tests\Fixtures\Settings\SchemaPropertySettings;
use Tests\Fixtures\Settings\ShipmentPricingSettings;

describe('settings manager', function (): void {
    test('exports persisted settings and can import them into a fresh state', function (): void {
        $businessEntity = BusinessEntity::query()->create([
            'name' => 'Finland',
            'country_code' => 'FI',
        ]);
        $subject = User::query()->create(['name' => 'Alice']);

        Settings::for($subject)->ownedBy($businessEntity)->save(
            new ShipmentPricingSettings(
                callBeforeDeliveryFee: 4.4,
                returnFreightDocFee: 0.4,
            ),
        );

        expect(DB::table('settings_values')->pluck('owner_type')->all())->toBe([
            'business_entity',
            'business_entity',
        ]);

        $snapshot = Settings::export(
            new SettingsQuery(
                settingsClass: ShipmentPricingSettings::class,
                target: new ResolutionTarget(Reference::from($businessEntity)),
            ),
        );

        Settings::for($subject)->ownedBy($businessEntity)->forget(
            ShipmentPricingSettings::class,
        );

        Settings::import($snapshot);

        $resolved = Settings::for($subject)
            ->ownedBy($businessEntity)
            ->fallbackToApp()
            ->get(ShipmentPricingSettings::class);

        expect($snapshot->entries)->toHaveCount(2)
            ->and($snapshot->entries[0]->target)->toBeInstanceOf(ResolutionTarget::class)
            ->and($resolved->callBeforeDeliveryFee)->toBe(4.4)
            ->and($resolved->returnFreightDocFee)->toBe(0.4);
    });

    test('rolls back all stored rows and audits when a full save fails mid-stream', function (): void {
        $carrier = Carrier::query()->create(['name' => 'DHL']);
        $subject = User::query()->create(['name' => 'Alice']);

        expect(fn (): mixed => Settings::for($subject)->ownedBy($carrier)->save(
            new AtomicSaveFailureSettings(
                plainValue: 'should-not-persist',
                brokenEncryptedValue: "\xB1\x31",
            ),
        ))->toThrow(SettingsSerializationException::class);

        expect(DB::table('settings_values')
            ->where('settings_class', AtomicSaveFailureSettings::class)
            ->count())->toBe(0)
            ->and(DB::table('settings_audits')
                ->where('settings_class', AtomicSaveFailureSettings::class)
                ->count())->toBe(0);
    });

    test('increments versions and rejects stale compare-and-set writes', function (): void {
        $carrier = Carrier::query()->create(['name' => 'FedEx']);
        $subject = User::query()->create(['name' => 'Alice']);

        $first = Settings::for($subject)->ownedBy($carrier)->compareAndSet(
            CarrierCredentialSettings::class,
            'apiToken',
            'alpha',
        );

        $second = Settings::for($subject)->ownedBy($carrier)->compareAndSet(
            CarrierCredentialSettings::class,
            'apiToken',
            'beta',
            $first->version,
        );

        expect($second->version)->toBe(2)
            ->and(fn (): mixed => Settings::for($subject)->ownedBy($carrier)->compareAndSet(
                CarrierCredentialSettings::class,
                'apiToken',
                'gamma',
                $first->version,
            ))->toThrow(ConcurrentSettingsWriteException::class);
    });

    test('records audit rows for saves deletes and imports', function (): void {
        $carrier = Carrier::query()->create(['name' => 'DHL']);
        $subject = User::query()->create(['name' => 'Bob']);

        Settings::for($subject)->ownedBy($carrier)->set(
            CarrierCredentialSettings::class,
            'apiToken',
            'secret',
        );

        $snapshot = Settings::export();

        Settings::for($subject)->ownedBy($carrier)->forget(
            CarrierCredentialSettings::class,
            'apiToken',
        );

        Settings::import($snapshot);

        $actions = DB::table('settings_audits')
            ->pluck('action')
            ->all();

        expect($actions)->toContain('saved')
            ->toContain('deleted')
            ->toContain('imported');
    });

    test('renames stored rows and audit history to a new settings class and namespace', function (): void {
        $carrier = Carrier::query()->create(['name' => 'FedEx']);
        $subject = User::query()->create(['name' => 'Alice']);

        Settings::for($subject)->ownedBy($carrier)->save(
            new LegacySchemaClassSettings(
                apiKey: 'legacy-key',
                enabled: false,
            ),
        );

        $migrated = Settings::rename(
            SettingsRename::settingsClass(
                LegacySchemaClassSettings::class,
                RenamedSchemaClassSettings::class,
                LegacySchemaClassSettings::namespace(),
                RenamedSchemaClassSettings::namespace(),
            ),
            $subject,
        );

        $resolved = Settings::for($subject)
            ->ownedBy($carrier)
            ->fallbackToApp()
            ->get(RenamedSchemaClassSettings::class);

        expect($migrated)->toBe(2)
            ->and($resolved->apiKey)->toBe('legacy-key')
            ->and($resolved->enabled)->toBeFalse();

        expect(Settings::inspect(
            new SettingsQuery(
                settingsClass: LegacySchemaClassSettings::class,
            ),
        ))->toHaveCount(0);

        expect(Settings::inspect(
            new SettingsQuery(
                settingsClass: RenamedSchemaClassSettings::class,
            ),
        ))->toHaveCount(2);

        expect(DB::table('settings_audits')
            ->where('settings_class', RenamedSchemaClassSettings::class)
            ->where('action', 'renamed')
            ->count())->toBe(2);
    });

    test('renames legacy properties for the current settings class and preserves scope', function (): void {
        $carrier = Carrier::query()->create(['name' => 'UPS']);
        $subject = User::query()->create(['name' => 'Bob']);
        $target = Reference::from($carrier);
        $subjectId = $subject->getKey();

        DB::table('settings_values')->insert([
            'settings_class' => SchemaPropertySettings::class,
            'settings_namespace' => SchemaPropertySettings::namespace(),
            'property' => 'legacyFee',
            'owner_type' => $target->type,
            'owner_id' => $target->id,
            'boundary_type' => '',
            'boundary_id' => '',
            'value' => json_encode([
                'cast' => null,
                'data' => 9.5,
                'encrypted' => false,
            ], \JSON_THROW_ON_ERROR),
            'version' => 1,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        DB::table('settings_audits')->insert([
            'action' => 'saved',
            'settings_class' => SchemaPropertySettings::class,
            'settings_namespace' => SchemaPropertySettings::namespace(),
            'property' => 'legacyFee',
            'owner_type' => $target->type,
            'owner_id' => $target->id,
            'boundary_type' => '',
            'boundary_id' => '',
            'subject_type' => 'user',
            'subject_id' => (string) $subjectId,
            'old_value' => json_encode(['data' => null], \JSON_THROW_ON_ERROR),
            'new_value' => json_encode(['data' => 9.5], \JSON_THROW_ON_ERROR),
            'context' => json_encode([], \JSON_THROW_ON_ERROR),
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $migrated = Settings::rename(
            SettingsRename::property(
                SchemaPropertySettings::class,
                'legacyFee',
                'deliveryFee',
                SchemaPropertySettings::namespace(),
            ),
            $subject,
        );

        $resolved = Settings::for($subject)
            ->ownedBy($carrier)
            ->fallbackToApp()
            ->get(SchemaPropertySettings::class);

        expect($migrated)->toBe(1)
            ->and($resolved->deliveryFee)->toBe(9.5);

        expect(Settings::inspect(
            new SettingsQuery(
                settingsClass: SchemaPropertySettings::class,
                property: 'legacyFee',
            ),
        ))->toHaveCount(0);

        expect(DB::table('settings_audits')
            ->where('settings_class', SchemaPropertySettings::class)
            ->where('property', 'deliveryFee')
            ->where('action', 'renamed')
            ->count())->toBe(1);
    });

    test('can inspect audit history preview rename conflicts and replay or rollback specific changes', function (): void {
        $carrier = Carrier::query()->create(['name' => 'DHL']);
        $subject = User::query()->create(['name' => 'Alice']);

        Settings::for($subject)->ownedBy($carrier)->set(
            CarrierCredentialSettings::class,
            'apiToken',
            'alpha',
        );

        Settings::for($subject)->ownedBy($carrier)->set(
            CarrierCredentialSettings::class,
            'apiToken',
            'beta',
        );

        expect(DB::table('settings_audits')->pluck('owner_type')->all())->toBe([
            'carrier',
            'carrier',
        ]);

        $audits = Settings::audit(
            new SettingsAuditQuery(
                action: 'saved',
                settingsClass: CarrierCredentialSettings::class,
                property: 'apiToken',
                target: new ResolutionTarget(Reference::from($carrier)),
            ),
        );

        expect($audits)->toHaveCount(2)
            ->and($audits[0]->property)->toBe('apiToken');

        $firstAudit = $audits[0];
        $secondAudit = $audits[1];

        Settings::rollback($secondAudit->id, $subject);

        $rolledBack = Settings::for($subject)
            ->ownedBy($carrier)
            ->fallbackToApp()
            ->value(CarrierCredentialSettings::class, 'apiToken');

        expect($rolledBack)->toBe('alpha');

        Settings::replay($secondAudit->id, $subject);

        $replayed = Settings::for($subject)
            ->ownedBy($carrier)
            ->fallbackToApp()
            ->value(CarrierCredentialSettings::class, 'apiToken');

        expect($replayed)->toBe('beta');

        Settings::rollback($firstAudit->id, $subject);

        $deleted = Settings::for($subject)
            ->ownedBy($carrier)
            ->fallbackToApp()
            ->value(CarrierCredentialSettings::class, 'apiToken');

        expect($deleted)->toBeNull();

        Settings::for($subject)->ownedBy($carrier)->save(
            new LegacySchemaClassSettings(
                apiKey: 'legacy',
                enabled: false,
            ),
        );

        Settings::for($subject)->ownedBy($carrier)->save(
            new RenamedSchemaClassSettings(
                apiKey: 'current',
                enabled: true,
            ),
        );

        $conflicts = Settings::inspectRenameConflicts(SettingsRename::settingsClass(
            LegacySchemaClassSettings::class,
            RenamedSchemaClassSettings::class,
            LegacySchemaClassSettings::namespace(),
            RenamedSchemaClassSettings::namespace(),
        ));

        expect($conflicts)->toHaveCount(2)
            ->and($conflicts[0]->toSettingsClass)->toBe(RenamedSchemaClassSettings::class)
            ->and($conflicts[0]->target->ownerType())->toBe('carrier');
    });

    test('throws when rolling back an unknown audit row', function (): void {
        expect(fn (): mixed => Settings::rollback(999_999))
            ->toThrow(SettingsAuditEntryNotFoundException::class);
    });

    test('throws when replay or rollback targets a non-property audit row', function (): void {
        $auditId = DB::table('settings_audits')->insertGetId([
            'action' => 'saved',
            'settings_class' => CarrierCredentialSettings::class,
            'settings_namespace' => CarrierCredentialSettings::namespace(),
            'property' => '',
            'owner_type' => '',
            'owner_id' => '',
            'boundary_type' => '',
            'boundary_id' => '',
            'subject_type' => '',
            'subject_id' => '',
            'old_value' => null,
            'new_value' => json_encode(['data' => 'beta'], \JSON_THROW_ON_ERROR),
            'context' => null,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        expect(fn (): mixed => Settings::rollback($auditId))
            ->toThrow(SettingsAuditEntryMustBePropertyScopedException::class);
    });

    test('throws when replay or rollback references a missing settings class', function (): void {
        $auditId = DB::table('settings_audits')->insertGetId([
            'action' => 'saved',
            'settings_class' => 'Tests\\Fixtures\\Settings\\MissingSettingsFixture',
            'settings_namespace' => CarrierCredentialSettings::namespace(),
            'property' => 'apiToken',
            'owner_type' => '',
            'owner_id' => '',
            'boundary_type' => '',
            'boundary_id' => '',
            'subject_type' => '',
            'subject_id' => '',
            'old_value' => json_encode(['data' => 'alpha'], \JSON_THROW_ON_ERROR),
            'new_value' => json_encode(['data' => 'beta'], \JSON_THROW_ON_ERROR),
            'context' => null,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        expect(fn (): mixed => Settings::rollback($auditId))
            ->toThrow(ReplayableSettingsClassDoesNotExistException::class);
    });

    test('throws when an audit row cannot be rolled back', function (): void {
        $auditId = DB::table('settings_audits')->insertGetId([
            'action' => 'deleted',
            'settings_class' => CarrierCredentialSettings::class,
            'settings_namespace' => CarrierCredentialSettings::namespace(),
            'property' => 'apiToken',
            'owner_type' => '',
            'owner_id' => '',
            'boundary_type' => '',
            'boundary_id' => '',
            'subject_type' => '',
            'subject_id' => '',
            'old_value' => null,
            'new_value' => json_encode(['data' => 'beta'], \JSON_THROW_ON_ERROR),
            'context' => null,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        expect(fn (): mixed => Settings::rollback($auditId))
            ->toThrow(SettingsAuditEntryCannotBeRolledBackException::class);
    });
});
