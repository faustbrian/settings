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
final class ReplayableSettingsClassDoesNotExistException extends SettingsAuditException
{
    /**
     * @param class-string|string $settingsClass
     */
    public static function forClass(string $settingsClass): self
    {
        return new self(sprintf(
            'Replay and rollback require an existing settings class. [%s] does not exist.',
            $settingsClass,
        ));
    }
}
