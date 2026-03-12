<?php declare(strict_types=1);

/**
 * Copyright (C) Brian Faust
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Cline\Settings\Support;

use Cline\Morphism\MorphKeyRegistry;
use Illuminate\Database\Eloquent\Model;

use function is_bool;
use function is_float;
use function is_int;
use function is_string;
use function resolve;

/**
 * Normalized polymorphic reference.
 *
 * References let the package persist owner and boundary identifiers without
 * coupling resolution targets to concrete model classes. A reference is the
 * canonical persistence form used by {@see ResolutionTarget} for owners,
 * boundaries, and optional subjects in audit trails.
 *
 * The type/id pair is deliberately storage-oriented:
 * - Eloquent models resolve to morph class plus primary key
 * - primitive scalar inputs resolve to the synthetic `scalar` type
 * - unsupported values return `null` so callers can decide whether that means
 *   "application scope", "no boundary", or an invalid calling pattern
 *
 * @author Brian Faust <brian@cline.sh>
 * @psalm-immutable
 */
final readonly class Reference
{
    /**
     * Empty strings are not expected during normal target construction, but
     * the type intentionally mirrors the persistence layer's broader input
     * surface so snapshot and audit reconstruction can remain lossless.
     */
    public function __construct(
        public string $type,
        public string $id,
    ) {}

    /**
     * Normalize a model, scalar, or existing reference into a stored reference.
     *
     * Returns `null` for unsupported values so callers can opt into
     * app-level or unscoped targets explicitly.
     *
     * Failure semantics are intentionally soft here. This method does not
     * throw for unknown types because conductor and manager APIs rely on `null`
     * to represent an omitted owner or boundary.
     */
    public static function from(mixed $value): ?self
    {
        if ($value === null) {
            return null;
        }

        if ($value instanceof self) {
            return $value;
        }

        if ($value instanceof Model) {
            /** @var MorphKeyRegistry $registry */
            $registry = resolve(MorphKeyRegistry::class);
            $key = $registry->getValue($value);

            return new self($value->getMorphClass(), (string) $key);
        }

        if (is_string($value)) {
            return new self('scalar', $value);
        }

        if (is_int($value)) {
            return new self('scalar', (string) $value);
        }

        if (is_float($value)) {
            return new self('scalar', (string) $value);
        }

        if (is_bool($value)) {
            return new self('scalar', $value ? '1' : '0');
        }

        return null;
    }
}
