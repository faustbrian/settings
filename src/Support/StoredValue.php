<?php declare(strict_types=1);

/**
 * Copyright (C) Brian Faust
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Cline\Settings\Support;

use DateTimeImmutable;
use DateTimeInterface;
use UnitEnum;

use const DATE_ATOM;

use function array_map;
use function is_array;

/**
 * Immutable decoded representation of one persisted settings property.
 *
 * This value object sits after repository lookup and codec decoding. It keeps
 * the original storage coordinates, version metadata, and encryption/cast
 * markers together with the runtime value that resolution logic actually uses.
 *
 * `StoredValue` is intentionally property-granular. Resolution, provenance,
 * audit logging, and debugging all operate on individual properties rather
 * than whole settings objects because each property may resolve from a
 * different target in the chain.
 *
 * @author Brian Faust <brian@cline.sh>
 * @psalm-immutable
 */
final readonly class StoredValue
{
    /**
     * @param null|string $cast
     *
     * The `$cast` value records which cast class encoded the payload in
     * storage when that metadata is available, while `$encrypted` reflects
     * whether the persisted payload was encrypted before being written.
     */
    public function __construct(
        public string $settingsClass,
        public string $namespace,
        public string $property,
        public mixed $value,
        public ResolutionTarget $target,
        public int $version,
        public bool $encrypted,
        public ?string $cast,
        public ?DateTimeImmutable $updatedAt = null,
    ) {}

    /**
     * @return array<string, mixed>
     *
     * Convert the decoded value into a serialization-safe array for debugging,
     * testing, transport, or audit output. Complex values are normalized to
     * deterministic scalar/array representations and no encryption is applied.
     */
    public function toArray(): array
    {
        return [
            'settings_class' => $this->settingsClass,
            'settings_namespace' => $this->namespace,
            'property' => $this->property,
            'value' => self::normalize($this->value),
            'owner_type' => $this->target->ownerType(),
            'owner_id' => $this->target->ownerId(),
            'boundary_type' => $this->target->boundaryType(),
            'boundary_id' => $this->target->boundaryId(),
            'version' => $this->version,
            'encrypted' => $this->encrypted,
            'cast' => $this->cast,
            'updated_at' => $this->updatedAt?->format(DATE_ATOM),
        ];
    }

    /**
     * Normalize nested runtime values into scalar-friendly structures.
     *
     * Date objects are converted to `DATE_ATOM`, enums are represented by
     * their case names, and nested arrays are normalized recursively so the
     * output is predictable across logs and assertions.
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
