<?php declare(strict_types=1);

/**
 * Copyright (C) Brian Faust
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Cline\Settings\Support;

/**
 * Immutable filter object for storage-facing settings queries.
 *
 * Query instances are passed to inspection, export, and pruning operations to
 * describe which persisted rows should be considered. Every field is optional;
 * `null` means "do not constrain by this axis".
 *
 * Filters are exact-match only:
 * - `settingsClass` scopes to one reflected settings type
 * - `namespace` scopes to the resolved storage namespace
 * - `property` scopes to one property within that namespace
 * - `target` scopes to one exact owner/boundary pair
 *
 * Because the object is immutable, it can be safely shared across services
 * without risk that one step broadens or narrows a query after another step
 * has already reasoned about it.
 *
 * @author Brian Faust <brian@cline.sh>
 * @psalm-immutable
 */
final readonly class SettingsQuery
{
    /**
     * @param null|string           $settingsClass Fully qualified settings
     *                                             class to match exactly
     * @param null|string           $namespace     Resolved storage namespace to
     *                                             match exactly
     * @param null|string           $property      Property name to match exactly
     * @param null|ResolutionTarget $target        Exact persisted target to
     *                                             match
     */
    public function __construct(
        public ?string $settingsClass = null,
        public ?string $namespace = null,
        public ?string $property = null,
        public ?ResolutionTarget $target = null,
    ) {}
}
