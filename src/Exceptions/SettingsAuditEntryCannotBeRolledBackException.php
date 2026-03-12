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
final class SettingsAuditEntryCannotBeRolledBackException extends SettingsAuditException
{
    public static function becauseValueIsMissing(): self
    {
        return new self('The audit entry does not contain a value that can be rolled back.');
    }
}
