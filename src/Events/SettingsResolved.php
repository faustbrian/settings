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
 * Event dispatched after a typed settings object has been resolved.
 *
 * By the time this event is emitted the manager has already walked the
 * supplied {@see ResolutionChain}, merged the winning stored values onto the
 * explicit settings defaults, and hydrated the concrete settings object. Listeners can
 * safely inspect the final object without re-running resolution.
 *
 * The payload intentionally includes the original chain and subject so audit
 * or telemetry listeners can correlate the final object with the request
 * context that produced it. Source metadata is not included here; listeners
 * that need per-property provenance should subscribe closer to the manager or
 * call the metadata-aware API directly.
 *
 * @author Brian Faust <brian@cline.sh>
 * @psalm-immutable
 */
final readonly class SettingsResolved
{
    /**
     * Create a new post-resolution event payload.
     *
     * @param class-string    $settingsClass The typed settings class that was
     *                                       resolved.
     * @param object          $settings      The fully hydrated settings object.
     * @param ResolutionChain $chain         The exact precedence chain that
     *                                       produced the final object, ordered
     *                                       from highest to lowest priority.
     * @param mixed           $subject       Optional caller context propagated by
     *                                       the manager for tracing and auditing.
     */
    public function __construct(
        public string $settingsClass,
        public object $settings,
        public ResolutionChain $chain,
        public mixed $subject,
    ) {}
}
