<?php declare(strict_types=1);

/**
 * Copyright (C) Brian Faust
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Cline\Settings\Exceptions;

/**
 * @author Brian Faust <brian@cline.sh>
 */
final class SettingsPruneDidNotReturnIntegerCountException extends SettingsRepositoryException
{
    public static function fromRepository(): self
    {
        return new self('Settings prune did not return an integer delete count.');
    }
}
