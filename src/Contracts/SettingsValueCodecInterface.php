<?php declare(strict_types=1);

/**
 * Copyright (C) Brian Faust
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Cline\Settings\Contracts;

use Cline\Settings\Support\SettingsDefinition;
use Cline\Settings\Support\StoredSettingRecord;
use Cline\Settings\Support\StoredValue;

/**
 * @author Brian Faust <brian@cline.sh>
 */
interface SettingsValueCodecInterface
{
    /**
     * @param class-string $settingsClass
     *
     * @return array{cast: null|string, data: mixed, encrypted: bool}
     */
    public function encode(SettingsDefinition $definition, string $settingsClass, string $property, mixed $value): array;

    /**
     * @param class-string         $settingsClass
     * @param array<string, mixed> $payload
     */
    public function decode(SettingsDefinition $definition, string $settingsClass, string $property, array $payload): mixed;

    /**
     * @param class-string $settingsClass
     */
    public function toStoredValue(
        SettingsDefinition $definition,
        string $settingsClass,
        StoredSettingRecord $record,
    ): StoredValue;
}
