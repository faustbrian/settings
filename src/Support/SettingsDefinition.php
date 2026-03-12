<?php declare(strict_types=1);

/**
 * Copyright (C) Brian Faust
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Cline\Settings\Support;

use Cline\Settings\Exceptions\InvalidSettingsPropertyException;
use Cline\Settings\Exceptions\MissingSettingsValueException;
use Cline\Settings\Settings;
use Cline\Struct\Contracts\CastInterface;
use Cline\Struct\Metadata\ClassMetadata;
use Cline\Struct\Metadata\MetadataFactory;
use Cline\Struct\Metadata\PropertyMetadata;
use Throwable;

use function array_key_exists;
use function get_object_vars;
use function is_subclass_of;
use function resolve;

/**
 * Reflected metadata for a typed settings class.
 *
 * Definitions cache the constructor-promoted properties that make up a
 * settings payload and provide the reflection-backed rules used throughout the
 * package to:
 * - derive the storage namespace
 * - discover defaults that participate in resolution fallback
 * - validate whether one property is part of the public settings contract
 * - identify property-level encryption and cast metadata
 * - hydrate objects from resolved persistence results
 * - extract current values back out for persistence or snapshotting
 *
 * The definition is authoritative for property membership. Only constructor
 * properties exposed through Struct metadata are treated as persisted
 * settings. Hydration is constructor-based so readonly settings objects are
 * built through the same contract they expose to package consumers.
 *
 * @author Brian Faust <brian@cline.sh>
 * @psalm-immutable
 */
final readonly class SettingsDefinition
{
    private ClassMetadata $metadata;

    /** @var array<string, PropertyMetadata> */
    private array $properties;

    /**
     * Build the reflected definition for one settings class.
     *
     * Reflection is performed once up front so later resolution and
     * persistence steps can reuse the normalized property map instead of
     * repeatedly scanning the class structure.
     *
     * @param class-string $settingsClass
     */
    public function __construct(
        public string $settingsClass,
    ) {
        try {
            $this->metadata = resolve(MetadataFactory::class)->for($settingsClass);
        } catch (Throwable) {
            $this->metadata = new MetadataFactory()->for($settingsClass);
        }

        $this->properties = $this->metadata->properties;
    }

    /**
     * Return the storage namespace for the reflected settings class.
     *
     * Classes extending the package base {@see Settings} type may override the
     * namespace used for persistence. Non-package classes fall back to their
     * fully qualified class name so external implementations still receive a
     * stable storage key.
     */
    public function namespace(): string
    {
        /** @var class-string<object> $class */
        $class = $this->settingsClass;

        if (!is_subclass_of($class, Settings::class)) {
            return $class;
        }

        return $class::namespace();
    }

    /**
     * Return explicit fallback values for hydrated settings properties.
     *
     * These defaults represent the last step in resolution precedence after
     * every target in a chain has been consulted. Only persisted properties are
     * included; unrelated keys returned by the settings class are discarded.
     *
     * @return array<string, mixed>
     */
    public function defaults(mixed $subject, ResolutionChain $chain): array
    {
        /** @var class-string<object> $class */
        $class = $this->settingsClass;

        if (!is_subclass_of($class, Settings::class)) {
            return [];
        }

        $defaults = $class::defaultsFor($subject, $chain);
        $filtered = [];

        foreach ($this->properties as $property) {
            if (!array_key_exists($property->name, $defaults)) {
                continue;
            }

            $filtered[$property->name] = $defaults[$property->name];
        }

        return $filtered;
    }

    /**
     * Return the constructor-promoted properties that form the settings payload.
     *
     * The returned map is keyed by property name for deterministic lookup in
     * validation, hydration, extraction, and attribute inspection routines.
     *
     * @return array<string, PropertyMetadata>
     */
    public function properties(): array
    {
        return $this->properties;
    }

    /**
     * Determine whether the settings class exposes the named property.
     *
     * Property existence is defined strictly by the public persisted contract,
     * not by private implementation details or dynamic attributes.
     */
    public function hasProperty(string $property): bool
    {
        return array_key_exists($property, $this->properties);
    }

    /**
     * Determine whether one property should be encrypted at rest.
     *
     * Encryption metadata is sourced from Struct's property metadata. This
     * method validates property membership first so callers cannot
     * accidentally ask about a non-persisted field.
     *
     * @throws InvalidSettingsPropertyException When the property is not part of
     *                                          the settings definition
     */
    public function isEncrypted(string $property): bool
    {
        $this->ensurePropertyExists($property);

        return $this->properties[$property]->isEncrypted;
    }

    /**
     * Resolve the configured cast for one property, if any.
     *
     * Cast instances are created on demand from the first declared
     * {@see CastWith} attribute. Returning `null` means the property is stored
     * and resolved without a package-level cast wrapper.
     *
     * @throws InvalidSettingsPropertyException When the property is not part of
     *                                          the settings definition
     */
    public function castFor(string $property): ?CastInterface
    {
        $this->ensurePropertyExists($property);

        return $this->properties[$property]->cast;
    }

    /**
     * Assert that the named property belongs to the settings payload.
     *
     * This is the guard used by higher-level helpers before they apply
     * persistence, casting, or metadata rules to a property.
     *
     * @throws InvalidSettingsPropertyException When the property is not part of
     *                                          the settings definition
     */
    public function ensurePropertyExists(string $property): void
    {
        if ($this->hasProperty($property)) {
            return;
        }

        throw InvalidSettingsPropertyException::forProperty($this->settingsClass, $property);
    }

    /**
     * @param array<string, mixed> $values
     *
     * Hydrate a settings instance from a fully resolved property map.
     *
     * Hydration happens after precedence resolution has already chosen one
     * concrete value per property. Every declared property must be present in
     * `$values`; missing entries are treated as a resolution failure rather than
     * defaulting silently at this stage.
     *
     * @throws MissingSettingsValueException When any declared property is
     *                                       absent from the resolved map
     */
    public function hydrate(array $values): object
    {
        foreach ($this->properties as $name => $property) {
            if (!array_key_exists($name, $values)) {
                throw MissingSettingsValueException::forProperty($this->settingsClass, $name);
            }
        }

        /** @var class-string<Settings> $class */
        $class = $this->settingsClass;

        return $class::create($values);
    }

    /**
     * Extract the current values of every public settings property.
     *
     * Extraction is used when typed settings objects need to be turned back
     * into a persistence-ready map, such as snapshot creation or bulk writes.
     * Uninitialized properties are rejected because the package cannot
     * serialize an indeterminate settings contract.
     *
     * @throws MissingSettingsValueException When a declared property has not
     *                                       been initialized on the object
     * @return array<string, mixed>
     */
    public function extract(object $settings): array
    {
        /** @var array<string, mixed> $values */
        $values = get_object_vars($settings);

        foreach ($this->properties as $name => $property) {
            if (!array_key_exists($name, $values)) {
                throw MissingSettingsValueException::forProperty($this->settingsClass, $name);
            }
        }

        return $values;
    }
}
