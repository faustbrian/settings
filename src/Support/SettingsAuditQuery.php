<?php declare(strict_types=1);

/**
 * Copyright (C) Brian Faust
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Cline\Settings\Support;

/**
 * Immutable filter object for audit-history inspection.
 *
 * @author Brian Faust <brian@cline.sh>
 * @psalm-immutable
 */
final readonly class SettingsAuditQuery
{
    public function __construct(
        public ?int $id = null,
        public ?string $action = null,
        public ?string $settingsClass = null,
        public ?string $namespace = null,
        public ?string $property = null,
        public ?ResolutionTarget $target = null,
    ) {}
}
