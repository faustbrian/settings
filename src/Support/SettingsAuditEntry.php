<?php declare(strict_types=1);

/**
 * Copyright (C) Brian Faust
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Cline\Settings\Support;

use DateTimeImmutable;

use const DATE_ATOM;

/**
 * Immutable audit-history entry used by operational tooling.
 *
 * @author Brian Faust <brian@cline.sh>
 * @psalm-immutable
 */
final readonly class SettingsAuditEntry
{
    public function __construct(
        public int $id,
        public string $action,
        public string $settingsClass,
        public string $namespace,
        public string $property,
        public ResolutionTarget $target,
        public ?Reference $subject,
        public mixed $oldValue,
        public mixed $newValue,
        public ?DateTimeImmutable $createdAt = null,
    ) {}

    /**
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return [
            'id' => $this->id,
            'action' => $this->action,
            'settings_class' => $this->settingsClass,
            'settings_namespace' => $this->namespace,
            'property' => $this->property,
            'target' => [
                'owner_type' => $this->target->ownerType(),
                'owner_id' => $this->target->ownerId(),
                'boundary_type' => $this->target->boundaryType(),
                'boundary_id' => $this->target->boundaryId(),
            ],
            'subject' => $this->subject instanceof Reference ? [
                'type' => $this->subject->type,
                'id' => $this->subject->id,
            ] : null,
            'old_value' => $this->oldValue,
            'new_value' => $this->newValue,
            'created_at' => $this->createdAt?->format(DATE_ATOM),
        ];
    }
}
