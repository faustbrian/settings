<?php declare(strict_types=1);

/**
 * Copyright (C) Brian Faust
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Cline\Settings\Events;

use Cline\Settings\Support\ResolutionChain;

/**
 * Event dispatched before a typed settings object is resolved.
 *
 * This is the earliest hook in the settings resolution lifecycle. It exposes
 * the requested settings class, the explicit precedence chain, and the caller
 * supplied subject before any repository lookups or object hydration occur.
 *
 * Listeners can use this event for tracing, diagnostics, or request-scoped
 * instrumentation. It is informational only: the event does not expose mutable
 * state and cannot alter the chain or short-circuit resolution. Any exception
 * thrown by a listener will, however, abort the manager operation before
 * storage is consulted.
 *
 * @author Brian Faust <brian@cline.sh>
 * @psalm-immutable
 */
final readonly class ResolvingSettings
{
    /**
     * Create a new pre-resolution event payload.
     *
     * @param class-string    $settingsClass The typed settings class being resolved.
     * @param ResolutionChain $chain         The explicit chain the manager will
     *                                       walk using first-match-wins
     *                                       precedence.
     * @param mixed           $subject       Optional caller context propagated by
     *                                       the manager for tracing and auditing.
     */
    public function __construct(
        public string $settingsClass,
        public ResolutionChain $chain,
        public mixed $subject,
    ) {}
}
