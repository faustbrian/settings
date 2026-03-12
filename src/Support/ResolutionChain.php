<?php declare(strict_types=1);

/**
 * Copyright (C) Brian Faust
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Cline\Settings\Support;

/**
 * Ordered list of exact resolution targets.
 *
 * Targets are evaluated from first to last. Earlier entries have higher
 * precedence than later entries. The chain is explicit by design: the package
 * does not infer fallbacks such as "business entity 3 should fall through to a
 * generic key" unless the caller supplies that target ordering.
 *
 * This object exists so conductors, events, and resolvers can pass the entire
 * precedence strategy around as a single immutable value instead of as loose
 * arrays. That keeps fallback intent visible and auditable at the call site.
 *
 * @author Brian Faust <brian@cline.sh>
 * @psalm-immutable
 */
final readonly class ResolutionChain
{
    /**
     * @param array<int, ResolutionTarget> $targets
     *
     * @psalm-param non-empty-list<ResolutionTarget>|array<int, ResolutionTarget> $targets
     *
     * Callers are expected to supply targets in lookup order from most
     * specific to least specific, typically ending with
     * {@see ResolutionTarget::app()} when application defaults should be part
     * of the chain.
     */
    public function __construct(
        public array $targets,
    ) {}
}
