<?php declare(strict_types=1);

/**
 * Copyright (C) Brian Faust
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Cline\Settings\Exceptions;

use function sprintf;

/**
 * @author Brian Faust <brian@cline.sh>
 */
final class SettingsAuditEntryNotFoundException extends AbstractSettingsAuditException
{
    public static function forId(int $auditId): self
    {
        return new self(sprintf(
            'The requested audit row [%d] does not exist.',
            $auditId,
        ));
    }
}
