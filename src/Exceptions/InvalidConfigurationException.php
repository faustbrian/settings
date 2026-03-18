<?php declare(strict_types=1);

/**
 * Copyright (C) Brian Faust
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Cline\Settings\Exceptions;

use InvalidArgumentException;

/**
 * Raised when package configuration contains mutually exclusive or invalid
 * settings.
 *
 * @author Brian Faust <brian@cline.sh>
 */
final class InvalidConfigurationException extends InvalidArgumentException implements SettingsExceptionInterface
{
    public static function conflictingMorphKeyMaps(): self
    {
        return new self(
            'Cannot configure both "morphKeyMap" and "enforceMorphKeyMap". Choose one or the other.',
        );
    }
}
