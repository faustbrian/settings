<?php declare(strict_types=1);

/**
 * Copyright (C) Brian Faust
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Cline\Settings;

use Cline\Settings\Support\ResolutionChain;
use Cline\Struct\AbstractData;

/**
 * Base class for typed settings objects.
 *
 * Concrete settings classes are immutable data objects built with constructor
 * property promotion. The package resolves persisted values into arrays and
 * then hydrates them through Struct's constructor-based creation pipeline.
 *
 * This keeps settings aligned with the rest of the cline package ecosystem:
 * - final readonly concrete classes
 * - constructor-driven hydration
 * - `with(...)` semantics for non-mutating changes
 * - Struct attribute and cast support instead of package-local casting rules
 *
 * @author Brian Faust <brian@cline.sh>
 * @psalm-immutable
 */
abstract readonly class Settings extends AbstractData
{
    /**
     * Return static fallback values for this settings class.
     *
     * Settings classes define schema; defaults are an explicit policy layer
     * rather than constructor signatures. Override this when the class has
     * context-free defaults.
     *
     * @return array<string, mixed>
     */
    public static function defaults(): array
    {
        return [];
    }

    /**
     * Return fallback values for this settings class in one resolution context.
     *
     * Override this when defaults depend on the acting subject or the
     * configured resolution chain. The default implementation delegates to the
     * context-free {@see self::defaults()} method.
     *
     * @return array<string, mixed>
     */
    public static function defaultsFor(mixed $subject, ResolutionChain $chain): array
    {
        return static::defaults();
    }

    /**
     * Return the logical namespace used for persistence.
     *
     * Override this when multiple classes should read from the same stored
     * namespace. The namespace is part of the persisted coordinate alongside
     * the property name and exact resolution target.
     */
    public static function namespace(): string
    {
        return static::class;
    }
}
