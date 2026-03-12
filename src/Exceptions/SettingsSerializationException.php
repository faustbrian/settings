<?php declare(strict_types=1);

/**
 * Copyright (C) Brian Faust
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Cline\Settings\Exceptions;

use RuntimeException;
use Throwable;

use function sprintf;

/**
 * Raised when a settings property cannot be serialized for storage.
 *
 * Repository implementations and casts should wrap lower-level encoding or
 * transport failures in this exception so callers can distinguish persistence
 * format problems from lookup or validation failures.
 *
 * @author Brian Faust <brian@cline.sh>
 */
final class SettingsSerializationException extends RuntimeException implements SettingsException
{
    /**
     * @param class-string $settingsClass
     *
     * Create an exception for one property that failed serialization.
     *
     * The original throwable is preserved as the previous exception so callers
     * can inspect the underlying encoder, JSON, or cast failure.
     */
    public static function forProperty(
        string $settingsClass,
        string $property,
        Throwable $previous,
    ): self {
        return new self(sprintf(
            'Unable to serialize [%s::%s].',
            $settingsClass,
            $property,
        ), previous: $previous);
    }
}
