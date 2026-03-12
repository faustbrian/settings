<?php declare(strict_types=1);

/**
 * Copyright (C) Brian Faust
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Cline\Settings\Support;

/**
 * Immutable schema-evolution instruction for persisted settings rows.
 *
 * A rename describes how existing stored coordinates should move when code
 * refactors rename a settings class, rename a property, or do both at once.
 * The operation is storage-oriented: it targets persisted rows and audit
 * history, not in-memory typed settings instances.
 *
 * @author Brian Faust <brian@cline.sh>
 * @psalm-immutable
 */
final readonly class SettingsRename
{
    /**
     * @param class-string $fromSettingsClass
     * @param class-string $toSettingsClass
     */
    public function __construct(
        public string $fromSettingsClass,
        public string $toSettingsClass,
        public ?string $fromProperty = null,
        public ?string $toProperty = null,
        public ?string $fromNamespace = null,
        public ?string $toNamespace = null,
    ) {}

    /**
     * @param class-string $fromSettingsClass
     * @param class-string $toSettingsClass
     */
    public static function settingsClass(
        string $fromSettingsClass,
        string $toSettingsClass,
        ?string $fromNamespace = null,
        ?string $toNamespace = null,
    ): self {
        return new self(
            fromSettingsClass: $fromSettingsClass,
            toSettingsClass: $toSettingsClass,
            fromNamespace: $fromNamespace,
            toNamespace: $toNamespace,
        );
    }

    /**
     * @param class-string $settingsClass
     */
    public static function property(
        string $settingsClass,
        string $fromProperty,
        string $toProperty,
        ?string $namespace = null,
    ): self {
        return new self(
            fromSettingsClass: $settingsClass,
            toSettingsClass: $settingsClass,
            fromProperty: $fromProperty,
            toProperty: $toProperty,
            fromNamespace: $namespace,
            toNamespace: $namespace,
        );
    }

    public function matchesProperty(): bool
    {
        return $this->fromProperty !== null;
    }

    public function targetProperty(string $sourceProperty): string
    {
        return $this->toProperty ?? $sourceProperty;
    }
}
