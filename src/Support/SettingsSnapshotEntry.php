<?php declare(strict_types=1);

/**
 * Copyright (C) Brian Faust
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Cline\Settings\Support;

use DateTimeInterface;
use UnitEnum;

use const DATE_ATOM;

use function array_map;
use function is_array;
use function is_int;
use function is_string;

/**
 * One persisted settings row represented inside a snapshot payload.
 *
 * Snapshot entries mirror the package's storage shape closely so exports and
 * imports can round-trip without re-resolving precedence. Each entry names the
 * settings class, namespace, property, target, stored value, and optimistic
 * locking version that were present at export time.
 *
 * The object is intentionally row-oriented rather than settings-oriented.
 * Snapshot import replays entries exactly as persisted, which preserves
 * property-level write semantics and version metadata.
 *
 * @author Brian Faust <brian@cline.sh>
 * @psalm-immutable
 */
final readonly class SettingsSnapshotEntry
{
    /**
     * @param string           $settingsClass Original settings class for the row
     * @param string           $namespace     Resolved storage namespace
     * @param string           $property      Persisted property name
     * @param mixed            $value         Stored value after package casting
     * @param ResolutionTarget $target        Exact owner/boundary target of the
     *                                        row
     * @param int              $version       Stored optimistic-lock version
     */
    public function __construct(
        public string $settingsClass,
        public string $namespace,
        public string $property,
        public mixed $value,
        public ResolutionTarget $target,
        public int $version,
    ) {}

    /**
     * @param array<string, mixed> $payload
     *
     * Reconstitute one snapshot entry from decoded JSON data.
     *
     * Missing or malformed fields fall back to conservative defaults so import
     * can continue processing partially valid snapshot documents. In
     * particular, absent targets collapse to the application scope and absent
     * versions fall back to `1`.
     */
    public static function fromArray(array $payload): self
    {
        $ownerType = $payload['owner_type'] ?? null;
        $ownerId = $payload['owner_id'] ?? null;
        $boundaryType = $payload['boundary_type'] ?? null;
        $boundaryId = $payload['boundary_id'] ?? null;

        return new self(
            is_string($payload['settings_class'] ?? null) ? $payload['settings_class'] : '',
            is_string($payload['settings_namespace'] ?? null) ? $payload['settings_namespace'] : '',
            is_string($payload['property'] ?? null) ? $payload['property'] : '',
            $payload['value'] ?? null,
            new ResolutionTarget(
                is_string($ownerType) && is_string($ownerId) ? new Reference($ownerType, $ownerId) : null,
                is_string($boundaryType) && is_string($boundaryId) ? new Reference($boundaryType, $boundaryId) : null,
            ),
            is_int($payload['version'] ?? null) ? $payload['version'] : 1,
        );
    }

    /**
     * Convert the entry into the normalized snapshot array shape.
     *
     * The output is stable and JSON-friendly. Complex scalar-like values such
     * as dates and enums are normalized so exported snapshots do not depend on
     * PHP object serialization details.
     *
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return [
            'settings_class' => $this->settingsClass,
            'settings_namespace' => $this->namespace,
            'property' => $this->property,
            'value' => self::normalize($this->value),
            'owner_type' => $this->target->owner?->type,
            'owner_id' => $this->target->owner?->id,
            'boundary_type' => $this->target->boundary?->type,
            'boundary_id' => $this->target->boundary?->id,
            'version' => $this->version,
        ];
    }

    /**
     * Normalize snapshot values into JSON-safe representations.
     *
     * Date objects are converted to `DATE_ATOM`, enums collapse to their case
     * name, and nested arrays are normalized recursively. All other values are
     * left unchanged so snapshot exports preserve the stored payload as closely
     * as possible.
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
