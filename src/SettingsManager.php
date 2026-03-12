<?php declare(strict_types=1);

/**
 * Copyright (C) Brian Faust
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Cline\Settings;

use Cline\Settings\Conductors\ResolutionConductor;
use Cline\Settings\Contracts\SettingsAuditLoggerInterface;
use Cline\Settings\Contracts\SettingsDefinitionResolverInterface;
use Cline\Settings\Contracts\SettingsManagerInterface;
use Cline\Settings\Contracts\SettingsRepositoryInterface;
use Cline\Settings\Contracts\SettingsValueCodecInterface;
use Cline\Settings\Database\Models\SettingAudit;
use Cline\Settings\Database\Models\StoredSetting;
use Cline\Settings\Events\ResolvingSettings;
use Cline\Settings\Events\SavingSettings;
use Cline\Settings\Events\SettingsResolved;
use Cline\Settings\Events\SettingsSaved;
use Cline\Settings\Exceptions\ReplayableSettingsClassDoesNotExistException;
use Cline\Settings\Exceptions\SettingsAuditEntryCannotBeRolledBackException;
use Cline\Settings\Exceptions\SettingsAuditEntryMustBePropertyScopedException;
use Cline\Settings\Exceptions\SettingsAuditEntryNotFoundException;
use Cline\Settings\Exceptions\SettingsRenameConflictException;
use Cline\Settings\Support\Reference;
use Cline\Settings\Support\ResolutionChain;
use Cline\Settings\Support\ResolutionTarget;
use Cline\Settings\Support\ResolvedSettings;
use Cline\Settings\Support\SettingsAuditEntry;
use Cline\Settings\Support\SettingsAuditQuery;
use Cline\Settings\Support\SettingsQuery;
use Cline\Settings\Support\SettingsRename;
use Cline\Settings\Support\SettingsRenameConflict;
use Cline\Settings\Support\SettingsSnapshot;
use Cline\Settings\Support\SettingsSnapshotEntry;
use Cline\Settings\Support\StoredSettingRecord;
use Cline\Settings\Support\StoredValue;
use DateTimeImmutable;
use DateTimeInterface;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\DB;

use function array_key_exists;
use function array_keys;
use function array_map;
use function class_exists;
use function config;
use function count;
use function event;
use function in_array;
use function is_array;
use function is_int;
use function is_string;

/**
 * Core application service for resolving and persisting typed settings.
 *
 * The manager coordinates the package's full lifecycle:
 * - resolving definition metadata for a settings class
 * - reading persisted values through an explicit {@see ResolutionChain}
 * - hydrating immutable settings objects from defaults plus stored overrides
 * - encoding values back into repository payloads on write
 * - emitting lifecycle events and audit records around persistence
 *
 * Resolution is intentionally explicit. The manager never invents fallback
 * order on its own; callers must provide the exact targets to inspect, ordered
 * from highest to lowest precedence. Persistence is similarly explicit and
 * always targets one concrete {@see ResolutionTarget} at a time.
 *
 * The manager's invariants are:
 * - property names must belong to the reflected settings definition
 * - encoded storage payloads are always produced through the codec layer
 * - repository inspection and snapshot export operate on persisted rows only
 * - destructive operations change storage, not defaults or sibling targets
 *
 * @author Brian Faust <brian@cline.sh>
 * @psalm-immutable
 */
final readonly class SettingsManager implements SettingsManagerInterface
{
    /**
     * Create a manager backed by the configured repository, definition cache,
     * value codec, and audit logger.
     *
     * These collaborators are injected so packages can replace persistence or
     * serialization behavior without changing the public resolution API.
     * Together they define the package's read pipeline, write pipeline, and
     * administrative tooling surface.
     */
    public function __construct(
        private SettingsRepositoryInterface $repository,
        private SettingsDefinitionResolverInterface $definitions,
        private SettingsValueCodecInterface $codec,
        private SettingsAuditLoggerInterface $audit,
    ) {}

    /**
     * Start a fluent resolution or persistence operation for a subject.
     *
     * The subject is not interpreted by the manager directly. It is carried
     * through lifecycle events and audit records so callers can attach actor or
     * request context without coupling the package to a specific application
     * model.
     */
    public function for(mixed $subject): ResolutionConductor
    {
        return new ResolutionConductor($this, $subject);
    }

    /**
     * @param class-string $settingsClass
     *
     * Resolve a typed settings object using the supplied precedence chain.
     *
     * This is the convenience API for callers that only need the hydrated
     * settings instance. Property provenance is discarded; use
     * {@see self::resolveWithMetadata()} when callers need to know which target
     * supplied each value.
     */
    public function resolve(string $settingsClass, mixed $subject, ResolutionChain $chain): object
    {
        return $this->resolveWithMetadata($settingsClass, $subject, $chain)->settings;
    }

    /**
     * @param class-string $settingsClass
     *
     * Resolve a typed settings object and retain per-property provenance.
     *
     * Resolution begins with the explicit settings defaults and then inspects the
     * provided chain from first target to last target for each property. The
     * first stored value found wins; later targets are ignored for that
     * property. If no stored value exists, the explicit default remains in place
     * and the property's source is recorded as `null`.
     *
     * A {@see ResolvingSettings} event is emitted before any repository reads
     * occur, and a {@see SettingsResolved} event is emitted after hydration
     * succeeds. Definition validation failures or codec decode failures bubble
     * up to the caller without being caught here.
     */
    public function resolveWithMetadata(
        string $settingsClass,
        mixed $subject,
        ResolutionChain $chain,
    ): ResolvedSettings {
        $definition = $this->definitions->resolve($settingsClass);
        $values = $definition->defaults($subject, $chain);
        $sources = [];

        event(
            new ResolvingSettings($settingsClass, $chain, $subject),
        );

        foreach (array_keys($definition->properties()) as $property) {
            $resolved = $this->resolveProperty($settingsClass, $property, $chain);

            if ($resolved['record'] instanceof StoredValue) {
                $values[$property] = $resolved['record']->value;
                $sources[$property] = $resolved['source'];

                continue;
            }

            if (!array_key_exists($property, $values)) {
                continue;
            }

            $sources[$property] = null;
        }

        $settings = $definition->hydrate($values);
        $resolvedSettings = new ResolvedSettings($settings, $sources);

        event(
            new SettingsResolved($settingsClass, $settings, $chain, $subject),
        );

        return $resolvedSettings;
    }

    /**
     * Persist every property from a typed settings object to one exact target.
     *
     * The full settings object is flattened through its definition metadata and
     * each property is written independently to the repository. Existing values
     * for the same coordinates are overwritten. Each property write also emits
     * an audit record, and the method dispatches lifecycle events before and
     * after the batch write.
     *
     * The write loop is wrapped in a single transaction so a failure on a
     * later property rolls back every earlier property write and its audit
     * rows. Audit history therefore only records successful whole-object
     * saves, not partial batches.
     */
    public function save(object $settings, mixed $subject, ResolutionTarget $target): object
    {
        $definition = $this->definitions->resolve($settings::class);
        $values = $definition->extract($settings);

        event(
            new SavingSettings($settings::class, $values, $target, $subject),
        );

        DB::transaction(function () use ($settings, $definition, $values, $target, $subject): void {
            foreach ($values as $property => $value) {
                $this->repository->save(
                    $settings::class,
                    $definition->namespace(),
                    $property,
                    $this->codec->encode($definition, $settings::class, $property, $value),
                    $target,
                );

                $this->audit->log(
                    'saved',
                    $settings::class,
                    $definition->namespace(),
                    $property,
                    $target,
                    $subject,
                    null,
                    $value,
                );
            }
        });

        event(
            new SettingsSaved($settings::class, $values, $target, $subject),
        );

        return $settings;
    }

    /**
     * @param class-string $settingsClass
     *
     * Retrieve one property value without hydrating the full settings object.
     *
     * The manager still applies the same explicit first-match-wins resolution
     * semantics as {@see self::resolveWithMetadata()}. If no stored value is
     * found anywhere in the chain, the provided `$default` is returned instead
     * of the class-level default.
     */
    public function getValue(
        string $settingsClass,
        string $property,
        mixed $subject,
        ResolutionChain $chain,
        mixed $default = null,
    ): mixed {
        $resolved = $this->resolveProperty($settingsClass, $property, $chain);

        return $resolved['record'] instanceof StoredValue ? $resolved['record']->value : $default;
    }

    /**
     * @param class-string $settingsClass
     *
     * Persist one property value for one exact target.
     *
     * The property name is validated against the settings definition before any
     * write occurs. The previous stored value is loaded first so the audit log
     * can capture the before/after transition. A {@see SavingSettings} event is
     * emitted before the write, and {@see SettingsSaved} is emitted afterwards.
     *
     * Missing current values are treated as normal inserts. Any encoding,
     * repository, or validation exception aborts the write and bubbles up to
     * the caller.
     */
    public function setValue(
        string $settingsClass,
        string $property,
        mixed $value,
        mixed $subject,
        ResolutionTarget $target,
    ): void {
        $definition = $this->definitions->resolve($settingsClass);
        $definition->ensurePropertyExists($property);

        $current = $this->repository->find(
            $settingsClass,
            $definition->namespace(),
            $property,
            $target,
        );

        event(
            new SavingSettings($settingsClass, [$property => $value], $target, $subject),
        );

        $this->repository->save(
            $settingsClass,
            $definition->namespace(),
            $property,
            $this->codec->encode($definition, $settingsClass, $property, $value),
            $target,
        );

        $this->audit->log(
            'saved',
            $settingsClass,
            $definition->namespace(),
            $property,
            $target,
            $subject,
            $current instanceof StoredSettingRecord
                ? $this->codec->decode($definition, $settingsClass, $property, $current->payload)
                : null,
            $value,
        );

        event(
            new SettingsSaved($settingsClass, [$property => $value], $target, $subject),
        );
    }

    /**
     * @param class-string $settingsClass
     *
     * Persist one property value using optimistic concurrency semantics.
     *
     * The optional `$expectedVersion` is forwarded to the repository so
     * implementations can reject stale writes. Unlike {@see self::setValue()},
     * this method returns the newly stored version metadata for callers that
     * need to continue a compare-and-swap workflow.
     *
     * The write is audited with the previous decoded value when one existed.
     * This low-level API intentionally does not emit lifecycle events, allowing
     * callers to use it for finer-grained concurrency control without double
     * dispatching package events. Repository implementations are expected to
     * signal version mismatches by throwing, most commonly via
     * `ConcurrentSettingsWriteException`.
     */
    public function compareAndSetValue(
        string $settingsClass,
        string $property,
        mixed $value,
        mixed $subject,
        ResolutionTarget $target,
        ?int $expectedVersion = null,
    ): StoredValue {
        $definition = $this->definitions->resolve($settingsClass);
        $definition->ensurePropertyExists($property);

        $current = $this->repository->find(
            $settingsClass,
            $definition->namespace(),
            $property,
            $target,
        );

        $record = $this->repository->save(
            $settingsClass,
            $definition->namespace(),
            $property,
            $this->codec->encode($definition, $settingsClass, $property, $value),
            $target,
            $expectedVersion,
        );

        $this->audit->log(
            'saved',
            $settingsClass,
            $definition->namespace(),
            $property,
            $target,
            $subject,
            $current instanceof StoredSettingRecord
                ? $this->codec->decode($definition, $settingsClass, $property, $current->payload)
                : null,
            $value,
        );

        return $this->codec->toStoredValue($definition, $settingsClass, $record);
    }

    /**
     * @param class-string $settingsClass
     *
     * Delete one stored property override from one exact target.
     *
     * Removing a value only affects persisted storage. Future resolution for
     * the property will continue through later entries in the chain and may
     * therefore fall back to another stored target or the class default.
     *
     * Audit logging occurs only when a row was actually deleted. Missing rows
     * are treated as a no-op and do not raise an exception.
     */
    public function forgetValue(
        string $settingsClass,
        string $property,
        mixed $subject,
        ResolutionTarget $target,
    ): void {
        $definition = $this->definitions->resolve($settingsClass);
        $definition->ensurePropertyExists($property);

        $current = $this->repository->find(
            $settingsClass,
            $definition->namespace(),
            $property,
            $target,
        );

        $deleted = $this->repository->delete(
            $settingsClass,
            $definition->namespace(),
            $property,
            $target,
        );

        if (!$deleted) {
            return;
        }

        $this->audit->log(
            'deleted',
            $settingsClass,
            $definition->namespace(),
            $property,
            $target,
            $subject,
            $current instanceof StoredSettingRecord
                ? $this->codec->decode($definition, $settingsClass, $property, $current->payload)
                : null,
        );
    }

    /**
     * @param class-string $settingsClass
     *
     * Delete every stored property for a settings class at one exact target.
     *
     * The manager snapshots the matching records before purging them so audit
     * entries can still include the deleted values. As with
     * {@see self::forgetValue()}, this only removes one target's overrides and
     * does not affect defaults or values stored at other targets in the
     * resolution chain.
     */
    public function forgetSettings(
        string $settingsClass,
        mixed $subject,
        ResolutionTarget $target,
    ): void {
        $definition = $this->definitions->resolve($settingsClass);
        $stored = $this->repository->all(
            new SettingsQuery(
                settingsClass: $settingsClass,
                namespace: $definition->namespace(),
                target: $target,
            ),
        );

        $deleted = $this->repository->purge(
            $settingsClass,
            $definition->namespace(),
            $target,
        );

        if ($deleted < 1) {
            return;
        }

        foreach ($stored as $record) {
            $this->audit->log(
                'purged',
                $settingsClass,
                $definition->namespace(),
                $record->property,
                $target,
                $subject,
                $this->codec->decode($definition, $settingsClass, $record->property, $record->payload),
            );
        }
    }

    /**
     * @return array<int, StoredValue>
     *
     * Inspect stored rows and decode them into transport-friendly value objects.
     *
     * This is the primary read path for diagnostics, export, and administrative
     * tooling. It bypasses typed object hydration and instead returns one entry
     * per persisted row, including target and version metadata.
     */
    public function inspect(SettingsQuery $query = new SettingsQuery()): array
    {
        return array_map(function (StoredSettingRecord $record): StoredValue {
            /** @var class-string $settingsClass */
            $settingsClass = $record->settingsClass;
            $definition = $this->definitions->resolve($settingsClass);

            return $this->codec->toStoredValue($definition, $settingsClass, $record);
        }, $this->repository->all($query));
    }

    /**
     * Export stored rows that match a query into a serializable snapshot.
     *
     * The snapshot preserves each entry's coordinates, decoded value, target,
     * and version so it can be written to JSON and later re-imported. This is a
     * repository-level export; it does not materialize full settings objects or
     * include explicit settings defaults that are not stored.
     */
    public function export(SettingsQuery $query = new SettingsQuery()): SettingsSnapshot
    {
        return new SettingsSnapshot(array_map(
            static fn (StoredValue $value): SettingsSnapshotEntry => new SettingsSnapshotEntry(
                $value->settingsClass,
                $value->namespace,
                $value->property,
                $value->value,
                $value->target,
                $value->version,
            ),
            $this->inspect($query),
        ));
    }

    /**
     * Import a previously exported snapshot into persistent storage.
     *
     * Each snapshot entry is written independently using the repository's
     * normal save path after being re-encoded according to the current
     * definition metadata. Existing rows for the same coordinates are
     * overwritten. The import is audited entry-by-entry but does not dispatch
     * the higher-level save events used by interactive writes.
     *
     * Because imports are replayed against current definitions, invalid
     * properties or values will fail at the point of entry processing and stop
     * the import unless the caller catches the exception.
     */
    public function import(SettingsSnapshot $snapshot, mixed $subject = null): void
    {
        foreach ($snapshot->entries as $entry) {
            /** @var class-string $settingsClass */
            $settingsClass = $entry->settingsClass;
            $definition = $this->definitions->resolve($settingsClass);

            $this->repository->save(
                $settingsClass,
                $entry->namespace,
                $entry->property,
                $this->codec->encode($definition, $settingsClass, $entry->property, $entry->value),
                $entry->target,
            );

            $this->audit->log(
                'imported',
                $settingsClass,
                $entry->namespace,
                $entry->property,
                $entry->target,
                $subject,
                null,
                $entry->value,
            );
        }
    }

    /**
     * Rename persisted settings coordinates to support schema evolution.
     *
     * This operation exists for refactors where code renames a settings class,
     * a property, or both while existing persisted rows still use the old
     * coordinates. Matching value rows are moved atomically, matching audit
     * rows are rewritten to the new coordinates, and a new `renamed` audit row
     * is emitted per moved value row.
     *
     * Renames are strict: if the destination coordinate already exists for any
     * affected target, the operation aborts with a conflict exception rather
     * than merging rows implicitly.
     */
    public function rename(SettingsRename $rename, mixed $subject = null): int
    {
        /** @var StoredSetting $valueModel */
        $valueModel = new (config('settings.models.stored_setting', StoredSetting::class))();

        /** @var array<int, StoredSetting> $records */
        $records = $this->buildRenameValueQuery($valueModel->newQuery(), $rename)
            ->orderBy('id')
            ->get()
            ->all();

        if ($records === []) {
            return 0;
        }

        $fromNamespace = $this->resolveRenameNamespace($rename->fromSettingsClass, $rename->fromNamespace);
        $toNamespace = $this->resolveRenameNamespace($rename->toSettingsClass, $rename->toNamespace);

        DB::transaction(function () use ($records, $rename, $fromNamespace, $toNamespace, $subject): void {
            /** @var StoredSetting $valueModel */
            $valueModel = new (config('settings.models.stored_setting', StoredSetting::class))();

            /** @var SettingAudit $auditModel */
            $auditModel = new (config('settings.models.setting_audit', SettingAudit::class))();

            foreach ($records as $record) {
                $sourceProperty = $record->getAttribute('property');
                $destinationProperty = $rename->targetProperty(
                    is_string($sourceProperty) ? $sourceProperty : '',
                );
                $target = $this->targetFromModel($record);

                $conflict = $valueModel->newQuery()
                    ->whereKeyNot($record->getKey())
                    ->where('settings_class', $rename->toSettingsClass)
                    ->where('settings_namespace', $toNamespace)
                    ->where('property', $destinationProperty)
                    ->where('owner_type', $target->ownerType())
                    ->where('owner_id', $target->ownerId())
                    ->where('boundary_type', $target->boundaryType())
                    ->where('boundary_id', $target->boundaryId())
                    ->exists();

                if ($conflict) {
                    throw SettingsRenameConflictException::forTarget(
                        $rename->toSettingsClass,
                        $destinationProperty,
                        $target,
                    );
                }
            }

            $this->buildRenameAuditQuery($auditModel->newQuery(), $rename, $fromNamespace)->update([
                'settings_class' => $rename->toSettingsClass,
                'settings_namespace' => $toNamespace,
                'property' => $rename->toProperty ?? DB::raw('property'),
            ]);

            foreach ($records as $record) {
                $sourceProperty = $record->getAttribute('property');
                $sourceProperty = is_string($sourceProperty) ? $sourceProperty : '';
                $destinationProperty = $rename->targetProperty($sourceProperty);
                $target = $this->targetFromModel($record);

                $record->setAttribute('settings_class', $rename->toSettingsClass);
                $record->setAttribute('settings_namespace', $toNamespace);
                $record->setAttribute('property', $destinationProperty);
                $record->save();

                $this->audit->log(
                    'renamed',
                    $rename->toSettingsClass,
                    $toNamespace,
                    $destinationProperty,
                    $target,
                    $subject,
                    [
                        'settings_class' => $rename->fromSettingsClass,
                        'settings_namespace' => $fromNamespace,
                        'property' => $sourceProperty,
                    ],
                    [
                        'settings_class' => $rename->toSettingsClass,
                        'settings_namespace' => $toNamespace,
                        'property' => $destinationProperty,
                    ],
                );
            }
        });

        return count($records);
    }

    /**
     * @return array<int, SettingsAuditEntry>
     */
    public function audit(SettingsAuditQuery $query = new SettingsAuditQuery()): array
    {
        /** @var SettingAudit $model */
        $model = new (config('settings.models.setting_audit', SettingAudit::class))();

        /** @var array<int, SettingAudit> $records */
        $records = $this->buildAuditQuery($model->newQuery(), $query)
            ->orderBy('id')
            ->get()
            ->all();

        return array_map($this->toAuditEntry(...), $records);
    }

    /**
     * @return array<int, SettingsRenameConflict>
     */
    public function inspectRenameConflicts(SettingsRename $rename): array
    {
        /** @var StoredSetting $valueModel */
        $valueModel = new (config('settings.models.stored_setting', StoredSetting::class))();

        /** @var array<int, StoredSetting> $records */
        $records = $this->buildRenameValueQuery($valueModel->newQuery(), $rename)
            ->orderBy('id')
            ->get()
            ->all();

        if ($records === []) {
            return [];
        }

        $toNamespace = $this->resolveRenameNamespace($rename->toSettingsClass, $rename->toNamespace);
        $fromNamespace = $this->resolveRenameNamespace($rename->fromSettingsClass, $rename->fromNamespace);
        $conflicts = [];

        foreach ($records as $record) {
            $sourceProperty = $record->getAttribute('property');
            $sourceProperty = is_string($sourceProperty) ? $sourceProperty : '';
            $destinationProperty = $rename->targetProperty($sourceProperty);
            $target = $this->targetFromModel($record);

            $conflict = $valueModel->newQuery()
                ->whereKeyNot($record->getKey())
                ->where('settings_class', $rename->toSettingsClass)
                ->where('settings_namespace', $toNamespace)
                ->where('property', $destinationProperty)
                ->where('owner_type', $target->ownerType())
                ->where('owner_id', $target->ownerId())
                ->where('boundary_type', $target->boundaryType())
                ->where('boundary_id', $target->boundaryId())
                ->exists();

            if (!$conflict) {
                continue;
            }

            $conflicts[] = new SettingsRenameConflict(
                $rename->fromSettingsClass,
                $fromNamespace,
                $sourceProperty,
                $rename->toSettingsClass,
                $toNamespace,
                $destinationProperty,
                $target,
            );
        }

        return $conflicts;
    }

    public function replay(int $auditId, mixed $subject = null): void
    {
        $entry = $this->findAuditEntry($auditId);
        $settingsClass = $this->assertReplayableSettingsClass($entry->settingsClass);

        if ($entry->action === 'deleted' || $entry->action === 'purged') {
            $this->forgetValue(
                $settingsClass,
                $entry->property,
                $subject,
                $entry->target,
            );

            return;
        }

        $this->setValue(
            $settingsClass,
            $entry->property,
            $entry->newValue,
            $subject,
            $entry->target,
        );
    }

    public function rollback(int $auditId, mixed $subject = null): void
    {
        $entry = $this->findAuditEntry($auditId);
        $settingsClass = $this->assertReplayableSettingsClass($entry->settingsClass);

        if (in_array($entry->action, ['saved', 'imported', 'renamed'], true)) {
            if ($entry->oldValue === null) {
                $this->forgetValue(
                    $settingsClass,
                    $entry->property,
                    $subject,
                    $entry->target,
                );

                return;
            }

            $this->setValue(
                $settingsClass,
                $entry->property,
                $entry->oldValue,
                $subject,
                $entry->target,
            );

            return;
        }

        if ($entry->oldValue === null) {
            throw SettingsAuditEntryCannotBeRolledBackException::becauseValueIsMissing();
        }

        $this->setValue(
            $settingsClass,
            $entry->property,
            $entry->oldValue,
            $subject,
            $entry->target,
        );
    }

    /**
     * Delete persisted rows older than the given cutoff.
     *
     * Pruning is delegated entirely to the repository and therefore applies to
     * stored rows only. Resolved defaults and current in-memory settings objects
     * are unaffected. The returned integer is the repository's count of deleted
     * rows.
     */
    public function prune(DateTimeInterface $before, SettingsQuery $query = new SettingsQuery()): int
    {
        return $this->repository->prune($before, $query);
    }

    /**
     * @param class-string $settingsClass
     *
     * @return array{found: bool, record: null|StoredValue, source: null|ResolutionTarget}
     *
     * Resolve one property by walking the explicit chain in precedence order.
     *
     * The repository is queried for each target until a stored row is found.
     * The first hit is decoded and returned together with the source target.
     * If no row exists anywhere in the chain the method reports `found=false`
     * and leaves default handling to the caller. This keeps the method focused
     * on storage lookup only; defaults remain a concern of the higher-level
     * resolution workflow.
     */
    private function resolveProperty(
        string $settingsClass,
        string $property,
        ResolutionChain $chain,
    ): array {
        $definition = $this->definitions->resolve($settingsClass);
        $definition->ensurePropertyExists($property);

        foreach ($chain->targets as $target) {
            $stored = $this->repository->find(
                $settingsClass,
                $definition->namespace(),
                $property,
                $target,
            );

            if (!$stored instanceof StoredSettingRecord) {
                continue;
            }

            return [
                'found' => true,
                'record' => $this->codec->toStoredValue($definition, $settingsClass, $stored),
                'source' => $target,
            ];
        }

        return [
            'found' => false,
            'record' => null,
            'source' => null,
        ];
    }

    /**
     * @param Builder<SettingAudit> $query
     *
     * @return Builder<SettingAudit>
     */
    private function buildAuditQuery(Builder $query, SettingsAuditQuery $settingsQuery): Builder
    {
        if ($settingsQuery->id !== null) {
            $query->whereKey($settingsQuery->id);
        }

        if ($settingsQuery->action !== null) {
            $query->where('action', $settingsQuery->action);
        }

        if ($settingsQuery->settingsClass !== null) {
            $query->where('settings_class', $settingsQuery->settingsClass);
        }

        if ($settingsQuery->namespace !== null) {
            $query->where('settings_namespace', $settingsQuery->namespace);
        }

        if ($settingsQuery->property !== null) {
            $query->where('property', $settingsQuery->property);
        }

        if ($settingsQuery->target instanceof ResolutionTarget) {
            $query
                ->where('owner_type', $settingsQuery->target->ownerType())
                ->where('owner_id', $settingsQuery->target->ownerId())
                ->where('boundary_type', $settingsQuery->target->boundaryType())
                ->where('boundary_id', $settingsQuery->target->boundaryId());
        }

        return $query;
    }

    /**
     * @param Builder<StoredSetting> $query
     *
     * @return Builder<StoredSetting>
     */
    private function buildRenameValueQuery(Builder $query, SettingsRename $rename): Builder
    {
        $query
            ->where('settings_class', $rename->fromSettingsClass)
            ->where('settings_namespace', $this->resolveRenameNamespace($rename->fromSettingsClass, $rename->fromNamespace));

        if ($rename->fromProperty !== null) {
            $query->where('property', $rename->fromProperty);
        }

        return $query;
    }

    /**
     * @param Builder<SettingAudit> $query
     *
     * @return Builder<SettingAudit>
     */
    private function buildRenameAuditQuery(Builder $query, SettingsRename $rename, string $fromNamespace): Builder
    {
        $query
            ->where('settings_class', $rename->fromSettingsClass)
            ->where('settings_namespace', $fromNamespace);

        if ($rename->fromProperty !== null) {
            $query->where('property', $rename->fromProperty);
        }

        return $query;
    }

    private function resolveRenameNamespace(string $settingsClass, ?string $namespace): string
    {
        if ($namespace !== null) {
            return $namespace;
        }

        if (class_exists($settingsClass)) {
            return $this->definitions->resolve($settingsClass)->namespace();
        }

        return $settingsClass;
    }

    private function findAuditEntry(int $auditId): SettingsAuditEntry
    {
        $entries = $this->audit(
            new SettingsAuditQuery(id: $auditId),
        );

        if ($entries === []) {
            throw SettingsAuditEntryNotFoundException::forId($auditId);
        }

        $entry = $entries[0];

        if ($entry->property === '') {
            throw SettingsAuditEntryMustBePropertyScopedException::forReplayOrRollback();
        }

        return $entry;
    }

    /**
     * @return class-string
     */
    private function assertReplayableSettingsClass(string $settingsClass): string
    {
        if (!class_exists($settingsClass)) {
            throw ReplayableSettingsClassDoesNotExistException::forClass($settingsClass);
        }

        return $settingsClass;
    }

    private function toAuditEntry(SettingAudit $record): SettingsAuditEntry
    {
        $id = $record->getKey();
        $action = $record->getAttribute('action');
        $settingsClass = $record->getAttribute('settings_class');
        $namespace = $record->getAttribute('settings_namespace');
        $property = $record->getAttribute('property');
        $subjectType = $record->getAttribute('subject_type');
        $subjectId = $record->getAttribute('subject_id');
        $oldValue = $record->getAttribute('old_value');
        $newValue = $record->getAttribute('new_value');
        $createdAt = $record->getAttribute('created_at');

        return new SettingsAuditEntry(
            is_int($id) ? $id : 0,
            is_string($action) ? $action : '',
            is_string($settingsClass) ? $settingsClass : '',
            is_string($namespace) ? $namespace : '',
            is_string($property) ? $property : '',
            $this->targetFromAuditModel($record),
            is_string($subjectType) && $subjectType !== '' && (is_string($subjectId) || is_int($subjectId)) && $subjectId !== ''
                ? new Reference($subjectType, (string) $subjectId)
                : null,
            is_array($oldValue) ? ($oldValue['data'] ?? null) : null,
            is_array($newValue) ? ($newValue['data'] ?? null) : null,
            is_string($createdAt) ? new DateTimeImmutable($createdAt) : null,
        );
    }

    private function targetFromModel(StoredSetting $record): ResolutionTarget
    {
        $ownerType = $record->getAttribute('owner_type');
        $ownerId = $record->getAttribute('owner_id');
        $boundaryType = $record->getAttribute('boundary_type');
        $boundaryId = $record->getAttribute('boundary_id');

        return new ResolutionTarget(
            is_string($ownerType) && $ownerType !== '' && (is_string($ownerId) || is_int($ownerId)) && $ownerId !== ''
                ? new Reference($ownerType, (string) $ownerId)
                : null,
            is_string($boundaryType) && $boundaryType !== '' && (is_string($boundaryId) || is_int($boundaryId)) && $boundaryId !== ''
                ? new Reference($boundaryType, (string) $boundaryId)
                : null,
        );
    }

    private function targetFromAuditModel(SettingAudit $record): ResolutionTarget
    {
        $ownerType = $record->getAttribute('owner_type');
        $ownerId = $record->getAttribute('owner_id');
        $boundaryType = $record->getAttribute('boundary_type');
        $boundaryId = $record->getAttribute('boundary_id');

        return new ResolutionTarget(
            is_string($ownerType) && $ownerType !== '' && (is_string($ownerId) || is_int($ownerId)) && $ownerId !== ''
                ? new Reference($ownerType, (string) $ownerId)
                : null,
            is_string($boundaryType) && $boundaryType !== '' && (is_string($boundaryId) || is_int($boundaryId)) && $boundaryId !== ''
                ? new Reference($boundaryType, (string) $boundaryId)
                : null,
        );
    }
}
