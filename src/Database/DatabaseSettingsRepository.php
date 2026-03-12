<?php declare(strict_types=1);

/**
 * Copyright (C) Brian Faust
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Cline\Settings\Database;

use Cline\Settings\Contracts\SettingsRepositoryInterface;
use Cline\Settings\Database\Models\StoredSetting;
use Cline\Settings\Exceptions\ConcurrentSettingsWriteException;
use Cline\Settings\Exceptions\SettingsPruneDidNotReturnIntegerCountException;
use Cline\Settings\Exceptions\SettingsPurgeDidNotReturnIntegerCountException;
use Cline\Settings\Exceptions\StoredSettingTransactionDidNotReturnRecordException;
use Cline\Settings\Support\Reference;
use Cline\Settings\Support\ResolutionTarget;
use Cline\Settings\Support\SettingsQuery;
use Cline\Settings\Support\StoredSettingRecord;
use DateTimeImmutable;
use DateTimeInterface;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\DB;

use function config;
use function is_array;
use function is_int;
use function is_string;

/**
 * Database-backed settings repository.
 *
 * This is the package's canonical persistence adapter for resolved settings
 * overrides. It stores one row per settings property and exact
 * owner-and-boundary target, leaving precedence decisions to the caller's
 * explicit resolution chain.
 *
 * The repository never performs fallback on its own. A lookup only matches the
 * precise coordinates supplied through `ResolutionTarget`. Higher layers
 * implement resolution by querying multiple targets in priority order.
 *
 * Writes are versioned and serialized through a transaction so concurrent
 * updates to the same property/target tuple can be detected. Deletions are
 * similarly exact unless the caller explicitly requests a namespace-wide purge.
 *
 * Coordinates are treated as immutable lookup keys.
 *
 * @author Brian Faust <brian@cline.sh>
 */
final class DatabaseSettingsRepository implements SettingsRepositoryInterface
{
    /**
     * Look up one persisted property override for an exact resolution target.
     *
     * No fallback is applied here. A `null` result means the requested
     * property is not stored for the exact owner/boundary tuple and callers
     * must continue their own resolution chain if broader scope values exist.
     */
    public function find(
        string $settingsClass,
        string $namespace,
        string $property,
        ResolutionTarget $target,
    ): ?StoredSettingRecord {
        /** @var StoredSetting $model */
        $model = new (config('settings.models.stored_setting', StoredSetting::class))();

        /** @var null|StoredSetting $record */
        $record = $this->baseQuery($model->newQuery(), new SettingsQuery(
            settingsClass: $settingsClass,
            namespace: $namespace,
            property: $property,
            target: $target,
        ))->first();

        return $record instanceof StoredSetting ? $this->toRecord($record) : null;
    }

    /**
     * Persist one property override for an exact resolution target.
     *
     * Existing rows are locked for update so the version check and write occur
     * atomically. When `$expectedVersion` is provided, the write becomes an
     * optimistic-concurrency operation and will fail with
     * `ConcurrentSettingsWriteException` if another process has already updated
     * the same property/target tuple.
     *
     * If no matching row exists, a new one is created with version `1`. If a
     * row already exists, its payload is replaced in full and the version is
     * incremented. This method does not merge payloads and does not cascade to
     * any broader or narrower scopes.
     *
     * @throws ConcurrentSettingsWriteException
     * @throws StoredSettingTransactionDidNotReturnRecordException
     */
    public function save(
        string $settingsClass,
        string $namespace,
        string $property,
        array $payload,
        ResolutionTarget $target,
        ?int $expectedVersion = null,
    ): StoredSettingRecord {
        /** @var StoredSetting $model */
        $model = new (config('settings.models.stored_setting', StoredSetting::class))();

        $storedRecord = DB::transaction(function () use (
            $model,
            $settingsClass,
            $namespace,
            $property,
            $payload,
            $target,
            $expectedVersion,
        ): StoredSettingRecord {
            /** @var null|StoredSetting $record */
            $record = $this->baseQuery($model->newQuery(), new SettingsQuery(
                settingsClass: $settingsClass,
                namespace: $namespace,
                property: $property,
                target: $target,
            ))->lockForUpdate()->first();

            $actualVersion = $record?->getAttribute('version');

            if ($expectedVersion !== null && $actualVersion !== $expectedVersion) {
                /** @var class-string $settingsClass */
                throw ConcurrentSettingsWriteException::forProperty(
                    $settingsClass,
                    $property,
                    $expectedVersion,
                    is_int($actualVersion) ? $actualVersion : null,
                );
            }

            if (!$record instanceof StoredSetting) {
                $record = $model->newInstance();
                $record->fill([
                    'settings_class' => $settingsClass,
                    'settings_namespace' => $namespace,
                    'property' => $property,
                    'owner_type' => $target->ownerType(),
                    'owner_id' => $target->ownerId(),
                    'boundary_type' => $target->boundaryType(),
                    'boundary_id' => $target->boundaryId(),
                    'version' => 0,
                ]);
            }

            $currentVersion = $record->getAttribute('version');

            $record->setAttribute('value', $payload);
            $record->setAttribute('version', (is_int($currentVersion) ? $currentVersion : 0) + 1);
            $record->save();

            return $this->toRecord($record);
        });

        if (!$storedRecord instanceof StoredSettingRecord) {
            throw StoredSettingTransactionDidNotReturnRecordException::duringSave();
        }

        return $storedRecord;
    }

    /**
     * Delete one persisted property override for an exact resolution target.
     *
     * The delete is intentionally narrow: only the precise property and target
     * tuple is removed. Broader namespace data and sibling targets remain
     * untouched. Returns `true` when at least one row was deleted.
     */
    public function delete(
        string $settingsClass,
        string $namespace,
        string $property,
        ResolutionTarget $target,
    ): bool {
        /** @var StoredSetting $model */
        $model = new (config('settings.models.stored_setting', StoredSetting::class))();

        return $this->baseQuery($model->newQuery(), new SettingsQuery(
            settingsClass: $settingsClass,
            namespace: $namespace,
            property: $property,
            target: $target,
        ))->delete() > 0;
    }

    /**
     * Delete every stored property override in a namespace for an exact target.
     *
     * This is the repository-level reset operation for one scope. It does not
     * affect the same namespace at other targets and does not remove settings
     * definitions or explicit settings defaults. The return value is the number of deleted
     * rows.
     *
     * @throws SettingsPurgeDidNotReturnIntegerCountException
     */
    public function purge(
        string $settingsClass,
        string $namespace,
        ResolutionTarget $target,
    ): int {
        /** @var StoredSetting $model */
        $model = new (config('settings.models.stored_setting', StoredSetting::class))();

        $deleted = $this->baseQuery($model->newQuery(), new SettingsQuery(
            settingsClass: $settingsClass,
            namespace: $namespace,
            target: $target,
        ))->delete();

        if (!is_int($deleted)) {
            throw SettingsPurgeDidNotReturnIntegerCountException::fromRepository();
        }

        return $deleted;
    }

    /**
     * Return all stored rows matching the supplied query constraints.
     *
     * This is an inspection-oriented method and applies no precedence logic.
     * Results are ordered by namespace and property to keep repository output
     * deterministic for tooling and tests.
     *
     * @return array<int, StoredSettingRecord>
     */
    public function all(SettingsQuery $query = new SettingsQuery()): array
    {
        /** @var StoredSetting $model */
        $model = new (config('settings.models.stored_setting', StoredSetting::class))();

        $records = [];
        $results = $this->baseQuery($model->newQuery(), $query)
            ->orderBy('settings_namespace')
            ->orderBy('property')
            ->get();

        /** @var iterable<StoredSetting> $results */
        foreach ($results as $record) {
            $records[] = $this->toRecord($record);
        }

        return $records;
    }

    /**
     * Delete rows older than the given timestamp within the supplied query
     * scope.
     *
     * Pruning is purely persistence-oriented cleanup. It does not understand
     * whether the removed rows were still reachable in a caller's resolution
     * chain; consumers are responsible for choosing a safe pruning boundary.
     *
     * @throws SettingsPruneDidNotReturnIntegerCountException
     */
    public function prune(DateTimeInterface $before, SettingsQuery $query = new SettingsQuery()): int
    {
        /** @var StoredSetting $model */
        $model = new (config('settings.models.stored_setting', StoredSetting::class))();

        $deleted = $this->baseQuery($model->newQuery(), $query)
            ->where('updated_at', '<', $before)
            ->delete();

        if (!is_int($deleted)) {
            throw SettingsPruneDidNotReturnIntegerCountException::fromRepository();
        }

        return $deleted;
    }

    /**
     * @param Builder<StoredSetting> $query
     *
     * @return Builder<StoredSetting>
     */
    private function baseQuery(Builder $query, SettingsQuery $settingsQuery): Builder
    {
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
     * Convert the database model into the immutable record consumed by the
     * resolution layer.
     *
     * The conversion normalizes missing or malformed scalar attributes to safe
     * empty values rather than letting loose database state leak through the
     * public repository contract. Payloads that fail array casting degrade to
     * an empty array. Invalid owner or boundary coordinates become `null`
     * references in the returned target.
     */
    private function toRecord(StoredSetting $record): StoredSettingRecord
    {
        $value = $record->getAttribute('value');

        /** @var array<string, mixed> $payload */
        $payload = is_array($value) ? $value : [];
        $settingsClass = $record->getAttribute('settings_class');
        $namespace = $record->getAttribute('settings_namespace');
        $property = $record->getAttribute('property');
        $ownerType = $record->getAttribute('owner_type');
        $ownerId = $record->getAttribute('owner_id');
        $boundaryType = $record->getAttribute('boundary_type');
        $boundaryId = $record->getAttribute('boundary_id');
        $version = $record->getAttribute('version');
        $updatedAt = $record->getAttribute('updated_at');

        return new StoredSettingRecord(
            is_string($settingsClass) ? $settingsClass : '',
            is_string($namespace) ? $namespace : '',
            is_string($property) ? $property : '',
            $payload,
            new ResolutionTarget(
                is_string($ownerType) && $ownerType !== '' && (is_string($ownerId) || is_int($ownerId)) && $ownerId !== ''
                    ? new Reference($ownerType, (string) $ownerId)
                    : null,
                is_string($boundaryType) && $boundaryType !== '' && (is_string($boundaryId) || is_int($boundaryId)) && $boundaryId !== ''
                    ? new Reference($boundaryType, (string) $boundaryId)
                    : null,
            ),
            is_int($version) ? $version : 0,
            is_string($updatedAt)
                ? new DateTimeImmutable($updatedAt)
                : null,
        );
    }
}
