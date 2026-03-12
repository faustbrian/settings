<?php declare(strict_types=1);

/**
 * Copyright (C) Brian Faust
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Cline\Settings\Support;

/**
 * Preview of a rename collision detected before schema migration runs.
 *
 * @author Brian Faust <brian@cline.sh>
 * @psalm-immutable
 */
final readonly class SettingsRenameConflict
{
    public function __construct(
        public string $fromSettingsClass,
        public string $fromNamespace,
        public string $fromProperty,
        public string $toSettingsClass,
        public string $toNamespace,
        public string $toProperty,
        public ResolutionTarget $target,
    ) {}

    /**
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return [
            'from_settings_class' => $this->fromSettingsClass,
            'from_namespace' => $this->fromNamespace,
            'from_property' => $this->fromProperty,
            'to_settings_class' => $this->toSettingsClass,
            'to_namespace' => $this->toNamespace,
            'to_property' => $this->toProperty,
            'target' => [
                'owner_type' => $this->target->ownerType(),
                'owner_id' => $this->target->ownerId(),
                'boundary_type' => $this->target->boundaryType(),
                'boundary_id' => $this->target->boundaryId(),
            ],
        ];
    }
}
