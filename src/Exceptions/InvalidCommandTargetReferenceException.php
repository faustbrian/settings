<?php declare(strict_types=1);

/**
 * Copyright (C) Brian Faust
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Cline\Settings\Exceptions;

use InvalidArgumentException;

use function sprintf;

/**
 * @author Brian Faust <brian@cline.sh>
 */
final class InvalidCommandTargetReferenceException extends InvalidArgumentException implements SettingsException
{
    public static function forValue(?string $value): self
    {
        if ($value === null) {
            return new self('Target reference must use the type:id format.');
        }

        return new self(sprintf(
            'Target reference [%s] must use the type:id format.',
            $value,
        ));
    }
}
