<?php declare(strict_types=1);

/**
 * Copyright (C) Brian Faust
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Cline\Settings\Commands;

use Cline\Settings\Facades\Settings;
use Cline\Settings\Support\SettingsRename;
use Cline\Settings\Support\SettingsRenameConflict;
use Illuminate\Console\Command;
use JsonException;
use Override;

use const JSON_PRETTY_PRINT;
use const JSON_THROW_ON_ERROR;

use function array_map;
use function class_exists;
use function is_string;
use function json_encode;

/**
 * @author Brian Faust <brian@cline.sh>
 */
final class InspectConflictsCommand extends Command
{
    #[Override()]
    protected $signature = 'settings:conflicts
        {from-settings : The original settings class}
        {to-settings : The destination settings class}
        {--from-property= : Limit to one original property}
        {--to-property= : Destination property name}
        {--from-namespace= : Original storage namespace}
        {--to-namespace= : Destination storage namespace}';

    #[Override()]
    protected $description = 'Preview schema rename conflicts';

    public function handle(): int
    {
        $fromSettings = $this->argument('from-settings');
        $toSettings = $this->argument('to-settings');
        $fromProperty = $this->option('from-property');
        $toProperty = $this->option('to-property');
        $fromNamespace = $this->option('from-namespace');
        $toNamespace = $this->option('to-namespace');

        if (!is_string($fromSettings) || !is_string($toSettings)) {
            $this->error('The command requires string source and destination settings classes.');

            return self::FAILURE;
        }

        if (!class_exists($fromSettings) || !class_exists($toSettings)) {
            $this->error('Both settings classes must exist.');

            return self::FAILURE;
        }

        /** @var class-string $fromSettings */
        /** @var class-string $toSettings */
        $rename = is_string($fromProperty) && is_string($toProperty)
            ? new SettingsRename(
                fromSettingsClass: $fromSettings,
                toSettingsClass: $toSettings,
                fromProperty: $fromProperty,
                toProperty: $toProperty,
                fromNamespace: is_string($fromNamespace) ? $fromNamespace : null,
                toNamespace: is_string($toNamespace) ? $toNamespace : null,
            )
            : SettingsRename::settingsClass(
                $fromSettings,
                $toSettings,
                is_string($fromNamespace) ? $fromNamespace : null,
                is_string($toNamespace) ? $toNamespace : null,
            );

        $conflicts = Settings::inspectRenameConflicts($rename);

        try {
            $this->line(json_encode(
                array_map(static fn (SettingsRenameConflict $conflict): array => $conflict->toArray(), $conflicts),
                JSON_PRETTY_PRINT | JSON_THROW_ON_ERROR,
            ));
        } catch (JsonException $jsonException) {
            $this->error($jsonException->getMessage());

            return self::FAILURE;
        }

        return self::SUCCESS;
    }
}
