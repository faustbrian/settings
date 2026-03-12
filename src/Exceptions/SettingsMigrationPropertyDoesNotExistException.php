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
final class SettingsMigrationPropertyDoesNotExistException extends SettingsMigrationException
{
    public static function forProperty(string $settingsClass, string $property): self
    {
        return new self(sprintf(
            'The [%s::$%s] setting does not exist for the selected target.',
            $settingsClass,
            $property,
        ));
    }
}
