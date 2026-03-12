<?php declare(strict_types=1);

/**
 * Copyright (C) Brian Faust
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Cline\Settings\Contracts;

use Cline\Settings\Support\ResolutionTarget;

/**
 * @author Brian Faust <brian@cline.sh>
 */
interface SettingsAuditLoggerInterface
{
    public function log(
        string $action,
        string $settingsClass,
        string $namespace,
        ?string $property,
        ResolutionTarget $target,
        mixed $subject = null,
        mixed $oldValue = null,
        mixed $newValue = null,
    ): void;
}
