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
final class SettingsMigrationFileDidNotReturnMigrationException extends SettingsMigrationException
{
    public static function fromPath(string $path): self
    {
        return new self(sprintf(
            'The settings migration file [%s] did not return a SettingsMigration instance.',
            $path,
        ));
    }
}
