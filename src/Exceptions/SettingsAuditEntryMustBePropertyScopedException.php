<?php declare(strict_types=1);

/**
 * Copyright (C) Brian Faust
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Cline\Settings\Exceptions;

/**
 * @author Brian Faust <brian@cline.sh>
 */
final class SettingsAuditEntryMustBePropertyScopedException extends AbstractSettingsAuditException
{
    public static function forReplayOrRollback(): self
    {
        return new self('Replay and rollback require a property-scoped audit row.');
    }
}
