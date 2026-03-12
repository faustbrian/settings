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
 * Raised when a property is not defined on a typed settings class.
 *
 * Guards raw property APIs from silently accepting unknown keys.
 * This keeps repository writes, deletes, provenance lookups, and low-level
 * value access aligned with the public properties declared on the settings
 * definition.
 *
 * @author Brian Faust <brian@cline.sh>
 */
final class InvalidSettingsPropertyException extends InvalidArgumentException implements SettingsException
{
    /**
     * @param class-string $settingsClass
     *
     * Create an exception for an unknown property name.
     *
     * The resulting message is suitable for both developer-facing diagnostics
     * and test assertions around invalid raw-property access.
     */
    public static function forProperty(string $settingsClass, string $property): self
    {
        return new self(sprintf(
            'Property [%s] is not defined on [%s].',
            $property,
            $settingsClass,
        ));
    }
}
