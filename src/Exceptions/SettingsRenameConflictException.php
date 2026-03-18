<?php declare(strict_types=1);

/**
 * Copyright (C) Brian Faust
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Cline\Settings\Exceptions;

use Cline\Settings\Support\ResolutionTarget;
use RuntimeException;

use function sprintf;

/**
 * Raised when a schema rename would overwrite an existing stored row.
 *
 * Renames are intentionally strict because silently merging two exact targets
 * would destroy data and make later rollback or audit analysis unreliable.
 *
 * @author Brian Faust <brian@cline.sh>
 */
final class SettingsRenameConflictException extends RuntimeException implements SettingsExceptionInterface
{
    public static function forTarget(
        string $settingsClass,
        string $property,
        ResolutionTarget $target,
    ): self {
        return new self(sprintf(
            'Cannot rename settings row to [%s::%s] at owner [%s:%s] and boundary [%s:%s] because that destination already exists.',
            $settingsClass,
            $property,
            $target->ownerType(),
            $target->ownerId(),
            $target->boundaryType(),
            $target->boundaryId(),
        ));
    }
}
