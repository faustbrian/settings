<?php declare(strict_types=1);

/**
 * Copyright (C) Brian Faust
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Cline\Settings\Migrations;

use Cline\Settings\Support\Reference;
use Cline\Settings\Support\ResolutionTarget;

/**
 * Entry point for target-scoped settings migration operations.
 *
 * The blueprint fixes one settings class and lets a migration select concrete
 * application, owner, or owner-plus-boundary targets before mutating values.
 *
 * @author Brian Faust <brian@cline.sh>
 * @psalm-immutable
 */
final readonly class SettingsBlueprint
{
    /**
     * @param class-string $settingsClass
     */
    public function __construct(
        private SettingsMigrator $migrator,
        private string $settingsClass,
    ) {}

    public function app(): ScopedSettingsBlueprint
    {
        return new ScopedSettingsBlueprint(
            $this->migrator,
            $this->settingsClass,
            ResolutionTarget::app(),
        );
    }

    public function ownedBy(mixed $owner = null, mixed $boundary = null): ScopedSettingsBlueprint
    {
        return new ScopedSettingsBlueprint(
            $this->migrator,
            $this->settingsClass,
            new ResolutionTarget(
                Reference::from($owner),
                Reference::from($boundary),
            ),
        );
    }
}
