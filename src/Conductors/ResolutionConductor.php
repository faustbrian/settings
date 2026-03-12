<?php declare(strict_types=1);

/**
 * Copyright (C) Brian Faust
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Cline\Settings\Conductors;

use Cline\Settings\Contracts\SettingsManagerInterface;
use Cline\Settings\Support\Reference;
use Cline\Settings\Support\ResolutionChain;
use Cline\Settings\Support\ResolutionTarget;
use Cline\Settings\Support\ResolvedSettings;
use Cline\Settings\Support\StoredValue;

/**
 * Fluent settings resolution API for a fixed subject.
 *
 * Binds a subject once and lets callers build an explicit owner and
 * boundary chain for reads, writes, provenance inspection, and cleanup.
 *
 * The conductor is intentionally stateful: every `ownedBy()` or fallback call
 * appends another exact `ResolutionTarget` in priority order. Reads walk that
 * chain from first to last, while writes and deletes always operate on the
 * first resolved target only. When no target is configured, the conductor
 * defaults to the application scope.
 *
 * @author Brian Faust <brian@cline.sh>
 */
final class ResolutionConductor
{
    /**
     * Ordered resolution targets from highest to lowest precedence.
     *
     * @var array<int, ResolutionTarget>
     */
    /** @var array<int, ResolutionTarget> */
    private array $targets = [];

    /**
     * Create a fluent conductor bound to one subject.
     *
     * The subject is carried through manager events so callers can correlate
     * settings resolution with the runtime context that triggered it.
     */
    public function __construct(
        private readonly SettingsManagerInterface $manager,
        private readonly mixed $subject,
    ) {}

    /**
     * Add an exact owner and optional boundary to the resolution chain.
     *
     * Each call appends a new target at the end of the chain. The first target
     * remains the write target for `save()`, `set()`, `compareAndSet()`, and
     * `forget()`, while later targets act as lower-priority fallbacks.
     */
    public function ownedBy(mixed $owner = null, mixed $boundary = null): self
    {
        $this->targets[] = new ResolutionTarget(
            Reference::from($owner),
            Reference::from($boundary),
        );

        return $this;
    }

    /**
     * Append a lower-priority fallback target to the chain.
     *
     * This is an alias for `ownedBy()` that documents intent at call sites
     * where the appended target should only be consulted after earlier entries.
     */
    public function fallbackTo(mixed $owner = null, mixed $boundary = null): self
    {
        return $this->ownedBy($owner, $boundary);
    }

    /**
     * Append the application-level fallback target to the chain.
     *
     * This is useful when scoped lookups should ultimately fall back to an
     * unowned global value instead of failing or relying on explicit settings
     * defaults.
     */
    public function fallbackToApp(): self
    {
        $this->targets[] = ResolutionTarget::app();

        return $this;
    }

    /**
     * @param class-string $settingsClass
     *
     * Resolve a typed settings object through the configured chain.
     *
     * Resolution is property-by-property. Higher-priority targets override
     * lower-priority targets, and missing properties may still be supplied by
     * explicit defaults declared on the settings class.
     */
    public function get(string $settingsClass): object
    {
        return $this->manager->resolve(
            $settingsClass,
            $this->subject,
            new ResolutionChain($this->resolvedTargets()),
        );
    }

    /**
     * @param class-string $settingsClass
     *
     * Resolve a typed settings object together with per-property source metadata.
     *
     * The returned `ResolvedSettings` instance exposes which target supplied
     * each property, making it suitable for diagnostics, admin UIs, and
     * "show effective configuration" workflows.
     */
    public function getResolved(string $settingsClass): ResolvedSettings
    {
        return $this->manager->resolveWithMetadata(
            $settingsClass,
            $this->subject,
            new ResolutionChain($this->resolvedTargets()),
        );
    }

    /**
     * Persist a fully typed settings object at the highest-priority target.
     *
     * Every public property on the object is written to the repository. This
     * produces a full override at the first resolved target rather than a
     * partial merge against lower-priority fallbacks.
     */
    public function save(object $settings): object
    {
        return $this->manager->save(
            $settings,
            $this->subject,
            $this->resolvedTargets()[0],
        );
    }

    /**
     * @param class-string $settingsClass
     *
     * Resolve a single property through the configured chain.
     *
     * Returns the provided default only when no stored value exists anywhere in
     * the explicit chain. Class defaults are handled by full typed resolution,
     * not by this raw property accessor.
     */
    public function value(string $settingsClass, string $property, mixed $default = null): mixed
    {
        return $this->manager->getValue(
            $settingsClass,
            $property,
            $this->subject,
            new ResolutionChain($this->resolvedTargets()),
            $default,
        );
    }

    /**
     * @param class-string $settingsClass
     *
     * Persist a single property at the highest-priority target.
     *
     * This is the low-level counterpart to `save()` and is appropriate when
     * callers are editing one property without materializing the full settings
     * object first.
     */
    public function set(string $settingsClass, string $property, mixed $value): self
    {
        $this->manager->setValue(
            $settingsClass,
            $property,
            $value,
            $this->subject,
            $this->resolvedTargets()[0],
        );

        return $this;
    }

    /**
     * @param class-string $settingsClass
     *
     * Persist one property using optimistic concurrency control.
     *
     * Implementations compare the supplied expected version against the current
     * stored row for the highest-priority target and fail when another writer
     * has modified the value first.
     */
    public function compareAndSet(
        string $settingsClass,
        string $property,
        mixed $value,
        ?int $expectedVersion = null,
    ): StoredValue {
        return $this->manager->compareAndSetValue(
            $settingsClass,
            $property,
            $value,
            $this->subject,
            $this->resolvedTargets()[0],
            $expectedVersion,
        );
    }

    /**
     * @param class-string $settingsClass
     *
     * Delete one property or all persisted values for the highest-priority target.
     *
     * Passing `null` removes every persisted property for the settings class at
     * the write target. Passing a property name deletes only that override and
     * allows lower-priority targets or defaults to become effective again.
     */
    public function forget(string $settingsClass, ?string $property = null): self
    {
        if ($property === null) {
            $this->manager->forgetSettings(
                $settingsClass,
                $this->subject,
                $this->resolvedTargets()[0],
            );

            return $this;
        }

        $this->manager->forgetValue(
            $settingsClass,
            $property,
            $this->subject,
            $this->resolvedTargets()[0],
        );

        return $this;
    }

    /**
     * @return array<int, ResolutionTarget>
     *
     * Resolve the effective target list, defaulting to the application scope.
     *
     * This guarantees that every conductor operation has at least one exact
     * target, even when the caller never configured explicit ownership.
     */
    private function resolvedTargets(): array
    {
        if ($this->targets === []) {
            return [ResolutionTarget::app()];
        }

        return $this->targets;
    }
}
