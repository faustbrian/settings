<?php declare(strict_types=1);

/**
 * Copyright (C) Brian Faust
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Cline\Settings\Support;

use Cline\Settings\Contracts\SettingsAuditLoggerInterface;
use Cline\Settings\Database\Models\SettingAudit;
use DateTimeInterface;
use UnitEnum;

use const DATE_ATOM;

use function array_map;
use function config;
use function is_array;

/**
 * Writes persistence-side audit entries for settings mutations.
 *
 * Audit logging is intentionally orthogonal to resolution and repository
 * writes. The manager can invoke this logger after a change is accepted
 * without coupling storage concerns to any specific audit schema.
 *
 * Entries are recorded per property and per exact target. This mirrors the
 * package's storage model, where each property override is persisted and
 * resolved independently, and ensures audit history reflects the same
 * precedence granularity that the resolver uses at runtime.
 *
 * @author Brian Faust <brian@cline.sh>
 */
final class SettingsAuditLogger implements SettingsAuditLoggerInterface
{
    /**
     * Persist one audit event for a settings mutation.
     *
     * `$property` may be null when the caller is recording a whole-settings
     * action rather than a single-property change, but the underlying audit
     * row still stores a deterministic string value. Subject resolution is
     * best-effort; unsupported subject types degrade to empty subject columns
     * instead of failing the write.
     */
    public function log(
        string $action,
        string $settingsClass,
        string $namespace,
        ?string $property,
        ResolutionTarget $target,
        mixed $subject = null,
        mixed $oldValue = null,
        mixed $newValue = null,
    ): void {
        /** @var SettingAudit $model */
        $model = new (config('settings.models.setting_audit', SettingAudit::class))();

        $subjectReference = Reference::from($subject);

        $model->newQuery()->create([
            'action' => $action,
            'settings_class' => $settingsClass,
            'settings_namespace' => $namespace,
            'property' => $property ?? '',
            'owner_type' => $target->ownerType(),
            'owner_id' => $target->ownerId(),
            'boundary_type' => $target->boundaryType(),
            'boundary_id' => $target->boundaryId(),
            'subject_type' => $subjectReference instanceof Reference ? $subjectReference->type : '',
            'subject_id' => $subjectReference instanceof Reference ? $subjectReference->id : '',
            'old_value' => ['data' => self::normalize($oldValue)],
            'new_value' => ['data' => self::normalize($newValue)],
            'context' => [],
        ]);
    }

    /**
     * Normalize arbitrary runtime values into audit-safe payloads.
     *
     * Audit rows should be JSON-serializable and stable across database
     * drivers, so date objects and enums are flattened before storage and
     * nested arrays are handled recursively.
     */
    private static function normalize(mixed $value): mixed
    {
        if ($value instanceof DateTimeInterface) {
            return $value->format(DATE_ATOM);
        }

        if ($value instanceof UnitEnum) {
            return $value->name;
        }

        if (is_array($value)) {
            return array_map(self::normalize(...), $value);
        }

        return $value;
    }
}
