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
 * Event dispatched after settings values have been persisted to one exact target.
 *
 * The payload contains the same logical write set the manager attempted to
 * store, allowing listeners to react without performing a second repository
 * lookup. It is emitted after the manager completes its save loop for the
 * current operation.
 *
 * Event listeners should treat this as a notification of intent plus observed
 * success from the manager's perspective. The event does not guarantee that
 * every surrounding side effect in userland completed atomically. Like its
 * pre-save counterpart, the values array may represent either a full object
 * save or a targeted one-property update depending on the originating API.
 *
 * @author Brian Faust <brian@cline.sh>
 * @psalm-immutable
 */
final readonly class SettingsSaved
{
    /**
     * @param class-string         $settingsClass The typed settings class that was
     *                                            written.
     * @param array<string, mixed> $values
     *
     * Create a new post-save event payload.
     * @param ResolutionTarget $target  The exact owner and boundary target
     *                                  the manager wrote to.
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
