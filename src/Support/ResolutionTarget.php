<?php declare(strict_types=1);

/**
 * Copyright (C) Brian Faust
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Cline\Settings\Support;

/**
 * Exact owner and boundary scope for one resolution step.
 *
 * A target identifies both who owns a value and the boundary where that value
 * applies, such as app-wide, carrier-specific, or organization-user specific.
 * The pair is treated as an exact coordinate in storage and lookup: changing
 * either owner or boundary means a different persisted setting row.
 *
 * Owners answer "who owns this override?" while boundaries answer "within
 * which narrower applicability context does it apply?". This distinction lets
 * the package represent layered cases such as "organization-owned, but only
 * for one member" without inventing ad hoc fallback keys.
 *
 * @author Brian Faust <brian@cline.sh>
 * @psalm-immutable
 */
final readonly class ResolutionTarget
{
    /**
     * @param null|Reference $owner    The owner of the stored value.
     * @param null|Reference $boundary The applicability boundary for the value.
     *
     * `null` plus `null` represents the application-level target.
     */
    public function __construct(
        public ?Reference $owner = null,
        public ?Reference $boundary = null,
    ) {}

    /**
     * Create the unscoped application-level target.
     *
     * This is the canonical fallback target for package-wide defaults and the
     * persistence representation used when no owner or boundary is supplied.
     */
    public static function app(): self
    {
        return new self();
    }

    /**
     * Return the normalized owner type used in persistence.
     *
     * Empty strings are used instead of null so database queries can match the
     * package's canonical application-scope representation consistently.
     */
    public function ownerType(): string
    {
        return $this->owner instanceof Reference ? $this->owner->type : '';
    }

    /**
     * Return the normalized owner identifier used in persistence.
     *
     * Empty strings indicate the target has no owner component.
     */
    public function ownerId(): string
    {
        return $this->owner instanceof Reference ? $this->owner->id : '';
    }

    /**
     * Return the normalized boundary type used in persistence.
     *
     * Empty strings indicate the target has no boundary component.
     */
    public function boundaryType(): string
    {
        return $this->boundary instanceof Reference ? $this->boundary->type : '';
    }

    /**
     * Return the normalized boundary identifier used in persistence.
     *
     * Empty strings indicate the target has no boundary component.
     */
    public function boundaryId(): string
    {
        return $this->boundary instanceof Reference ? $this->boundary->id : '';
    }
}
