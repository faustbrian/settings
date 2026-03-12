<?php declare(strict_types=1);

/**
 * Copyright (C) Brian Faust
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Cline\Settings\Support;

use Cline\Settings\Exceptions\InvalidSettingsPropertyException;

use function array_key_exists;

/**
 * Resolved typed settings plus per-property provenance metadata.
 *
 * The `sources` map contains one entry per resolved property. A `null` source
 * means the property value came from the class default rather than storage.
 * This object is produced after resolution precedence has been fully applied:
 * every property has a final typed value, and every property can also report
 * which target in the chain supplied that value.
 *
 * Consumers use this metadata when they need more than the hydrated settings
 * object alone, such as explaining why one value won, showing inheritance in a
 * UI, or deciding whether a write would override a default or a persisted row.
 *
 * @author Brian Faust <brian@cline.sh>
 * @psalm-immutable
 */
final readonly class ResolvedSettings
{
    /**
     * @param array<string, null|ResolutionTarget> $sources
     *
     * The `sources` array must contain every resolved property. Missing keys
     * are treated as a contract violation and surfaced when queried.
     */
    public function __construct(
        public object $settings,
        public array $sources,
    ) {}

    /**
     * Return the target that supplied the resolved value for one property.
     *
     * A `null` return value means resolution fell through every persisted
     * target in the chain and the property's class default won instead.
     *
     * @throws InvalidSettingsPropertyException When provenance was not recorded
     *                                          for the requested property
     */
    public function sourceFor(string $property): ?ResolutionTarget
    {
        if (!array_key_exists($property, $this->sources)) {
            throw InvalidSettingsPropertyException::forProperty($this->settings::class, $property);
        }

        return $this->sources[$property];
    }

    /**
     * Determine whether a property resolved from its class-level default.
     *
     * This is equivalent to checking whether {@see sourceFor()} returns
     * `null`, but keeps call sites explicit about the fallback they are asking
     * about.
     *
     * @throws InvalidSettingsPropertyException When provenance was not recorded
     *                                          for the requested property
     */
    public function usesDefault(string $property): bool
    {
        return !$this->sourceFor($property) instanceof ResolutionTarget;
    }
}
