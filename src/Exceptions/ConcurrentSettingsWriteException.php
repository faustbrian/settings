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
 * Raised when an optimistic compare-and-set write loses a race.
 *
 * This signals that the stored row version changed between read and write,
 * so the caller must re-read the latest value before attempting another
 * update.
 *
 * @author Brian Faust <brian@cline.sh>
 */
final class ConcurrentSettingsWriteException extends RuntimeException implements SettingsExceptionInterface
{
    /**
     * Create an exception describing the failed optimistic write.
     *
     * A `null` expected version represents "write only if absent", while a
     * `null` actual version indicates that no stored row currently exists.
     *
     * @param class-string $settingsClass
     */
    public static function forProperty(
        string $settingsClass,
        string $property,
        ?int $expectedVersion,
        ?int $actualVersion,
    ): self {
        return new self(sprintf(
            'Concurrent write detected for [%s::%s]. Expected version [%s] but found [%s].',
            $settingsClass,
            $property,
            $expectedVersion === null ? 'null' : (string) $expectedVersion,
            $actualVersion === null ? 'null' : (string) $actualVersion,
        ));
    }
}
