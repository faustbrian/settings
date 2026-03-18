<?php declare(strict_types=1);

/**
 * Copyright (C) Brian Faust
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Cline\Settings\Exceptions;

use JsonException;
use RuntimeException;

/**
 * @author Brian Faust <brian@cline.sh>
 */
final class SettingsSnapshotEncodingException extends RuntimeException implements SettingsExceptionInterface
{
    public static function fromJsonException(JsonException $previous): self
    {
        return new self($previous->getMessage(), $previous->getCode(), $previous);
    }
}
