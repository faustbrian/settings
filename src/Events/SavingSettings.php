<?php declare(strict_types=1);

/**
 * Copyright (C) Brian Faust
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Cline\Settings\Events;

use Cline\Settings\Support\ResolutionTarget;

/**
 * Event dispatched before settings values are persisted to one exact target.
 *
 * The payload represents the write set as the manager sees it immediately
 * before encoding and repository persistence. Listeners can use this hook for
 * observability, policy enforcement, or side-channel auditing around writes to
 * a specific target.
 *
 * The event is descriptive rather than transactional. Throwing from a
 * listener will prevent the write from completing, but the event itself does
 * not offer a mutation API for changing the values in-flight. Consumers should
 * also note that batch saves reuse this event for the whole property set,
 * while single-property writes dispatch it with a one-entry values array.
 *
 * @author Brian Faust <brian@cline.sh>
 * @psalm-immutable
 */
final readonly class SavingSettings
{
    /**
     * @param class-string         $settingsClass The typed settings class being
     *                                            written.
     * @param array<string, mixed> $values
     *
     * Create a new pre-save event payload.
     * @param ResolutionTarget $target  The exact owner and boundary target
     *                                  the manager intends to write.
     * @param mixed            $subject Optional caller context propagated by
     *                                  the manager for tracing and auditing.
     */
    public function __construct(
        public string $settingsClass,
        public array $values,
        public ResolutionTarget $target,
        public mixed $subject,
    ) {}
}
