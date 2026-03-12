<?php declare(strict_types=1);

/**
 * Copyright (C) Brian Faust
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Cline\Settings\Support;

use Cline\Settings\Contracts\SettingsDefinitionResolverInterface;

/**
 * Resolves and memoizes settings definitions by settings class name.
 *
 * Settings definitions are reflection-heavy metadata objects that describe
 * the public properties, default values, cast attributes, and encryption
 * attributes for a typed settings class. The resolver centralizes that work
 * so the package reflects each settings class once per container lifecycle
 * instead of once per read/write operation.
 *
 * The cache is intentionally process-local. Definitions are treated as static
 * for the lifetime of the PHP process, which matches how package consumers
 * define typed settings classes in code.
 *
 * @author Brian Faust <brian@cline.sh>
 */
final class SettingsDefinitionResolver implements SettingsDefinitionResolverInterface
{
    /**
     * Cached settings definitions indexed by their fully qualified class name.
     *
     * @var array<class-string, SettingsDefinition>
     */
    private array $definitions = [];

    /**
     * Return the cached definition for the given settings class, creating it
     * on first resolution.
     *
     * @param class-string $settingsClass
     *
     * Repeated calls for the same class return the same in-memory definition
     * instance. Any reflection or attribute validation errors surface from the
     * {@see SettingsDefinition} constructor during the first resolution.
     */
    public function resolve(string $settingsClass): SettingsDefinition
    {
        return $this->definitions[$settingsClass] ??= new SettingsDefinition($settingsClass);
    }
}
