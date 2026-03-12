<?php declare(strict_types=1);

/**
 * Copyright (C) Brian Faust
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Cline\Settings\Facades;

use Cline\Settings\Conductors\ResolutionConductor;
use Cline\Settings\Contracts\SettingsManagerInterface;
use Cline\Settings\Support\ResolutionChain;
use Cline\Settings\Support\ResolutionTarget;
use Cline\Settings\Support\ResolvedSettings;
use Cline\Settings\Support\SettingsAuditEntry;
use Cline\Settings\Support\SettingsAuditQuery;
use Cline\Settings\Support\SettingsQuery;
use Cline\Settings\Support\SettingsRename;
use Cline\Settings\Support\SettingsRenameConflict;
use Cline\Settings\Support\SettingsSnapshot;
use Cline\Settings\Support\StoredValue;
use Illuminate\Support\Facades\Facade;

/**
 * Static entry point for the package's settings manager.
 *
 * The facade fronts the full typed-settings lifecycle:
 * - resolve values through an ordered {@see ResolutionChain}
 * - inspect or export raw persisted rows
 * - import snapshots back into storage
 * - write, forget, compare-and-set, and prune persisted values
 *
 * Most methods either read from or mutate the underlying settings store, so
 * callers should treat facade operations as persistence boundaries rather than
 * cheap in-memory helpers. Resolution methods honor package precedence rules:
 * the first stored value found in the supplied chain wins, and unresolved
 * properties fall back to explicit settings defaults where available.
 *
 * @method static array<int, SettingsAuditEntry>     audit(SettingsAuditQuery $query = null)                                                                                                           Return audit-history entries that match the supplied filters
 * @method static StoredValue                        compareAndSetValue(string $settingsClass, string $property, mixed $value, mixed $subject, ResolutionTarget $target, ?int $expectedVersion = null) Persist a property only when the current version matches the optimistic-lock expectation
 * @method static SettingsSnapshot                   export(SettingsQuery $query = null)                                                                                                               Export matching stored rows into a portable snapshot payload
 * @method static ResolutionConductor                for(mixed $subject)                                                                                                                               Begin fluent resolution and persistence operations for one subject context
 * @method static void                               forgetSettings(string $settingsClass, mixed $subject, ResolutionTarget $target)                                                                   Delete every stored property row for one settings class at the given target
 * @method static void                               forgetValue(string $settingsClass, string $property, mixed $subject, ResolutionTarget $target)                                                    Delete one stored property row at the given target
 * @method static mixed                              getValue(string $settingsClass, string $property, mixed $subject, ResolutionChain $chain, mixed $default = null)                                  Resolve one property through the supplied precedence chain and optional fallback
 * @method static void                               import(SettingsSnapshot $snapshot, mixed $subject = null)                                                                                         Write a snapshot payload back into storage for the provided subject context
 * @method static array<int, StoredValue>            inspect(SettingsQuery $query = null)                                                                                                              Return raw stored rows that match the supplied query filters
 * @method static array<int, SettingsRenameConflict> inspectRenameConflicts(SettingsRename $rename)                                                                                                    Preview schema-rename collisions without mutating storage
 * @method static int                                prune(\DateTimeInterface $before, SettingsQuery $query = null)                                                                                    Delete rows older than the cutoff and return the number removed
 * @method static int                                rename(SettingsRename $rename, mixed $subject = null)                                                                                             Rename persisted settings coordinates to support schema evolution
 * @method static void                               replay(int $auditId, mixed $subject = null)                                                                                                       Reapply the final state recorded by one audit row
 * @method static object                             resolve(string $settingsClass, mixed $subject, ResolutionChain $chain)                                                                            Hydrate a typed settings object using stored values and explicit settings defaults
 * @method static ResolvedSettings                   resolveWithMetadata(string $settingsClass, mixed $subject, ResolutionChain $chain)                                                                Hydrate settings together with per-property provenance metadata
 * @method static void                               rollback(int $auditId, mixed $subject = null)                                                                                                     Restore the state that existed before one audit row was recorded
 * @method static void                               setValue(string $settingsClass, string $property, mixed $value, mixed $subject, ResolutionTarget $target)                                         Persist one property value for an exact target
 *
 * @author Brian Faust <brian@cline.sh>
 */
final class Settings extends Facade
{
    /**
     * Resolve the service-container binding used by the facade.
     *
     * Returning the manager contract keeps the facade aligned with the
     * package's replaceable orchestration boundary.
     */
    protected static function getFacadeAccessor(): string
    {
        return SettingsManagerInterface::class;
    }
}
