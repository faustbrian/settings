<?php declare(strict_types=1);

/**
 * Copyright (C) Brian Faust
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Cline\Settings\Exceptions;

use RuntimeException;

use function sprintf;

/**
 * Raised when a required typed settings property cannot be resolved.
 *
 * This is thrown when neither persistence nor explicit settings defaults provide a
 * value for a required public property.
 *
 * The exception is typically raised during hydration of a typed settings
 * object, after the resolution chain has been exhausted and before the object
 * can be safely returned to the caller.
 *
 * @author Brian Faust <brian@cline.sh>
 */
final class MissingSettingsValueException extends RuntimeException implements SettingsExceptionInterface
{
    /**
     * @param class-string $settingsClass
     *
     * Create an exception for one unresolved typed property.
     *
     * This identifies the concrete settings class and property that prevented
     * a complete, type-safe settings payload from being built.
     */
    public static function forProperty(string $settingsClass, string $property): self
    {
        return new self(sprintf(
            'Missing required value for [%s::%s].',
            $settingsClass,
            $property,
        ));
    }
}
