<?php declare(strict_types=1);

/**
 * Copyright (C) Brian Faust
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Cline\Settings\Commands;

use Cline\Settings\Facades\Settings;
use Cline\Settings\Support\SettingsQuery;
use Illuminate\Console\Command;
use JsonException;
use Override;

use const JSON_PRETTY_PRINT;
use const JSON_THROW_ON_ERROR;

use function is_string;
use function json_encode;

/**
 * Lists persisted settings rows as a JSON inspection payload.
 *
 * This command is a read-only operator tool for examining the storage layer
 * after resolution or writes have already happened elsewhere. It bypasses
 * typed hydration and emits the raw inspected rows returned by the manager so
 * callers can audit namespaces, targets, versions, and serialized values.
 *
 * Filtering mirrors the package's persistence axes:
 * - `--settings` narrows by one settings class
 * - `--property` narrows by one property within that class
 * - `--target` narrows by one exact persisted target
 *
 * The command never mutates storage. Its only failure mode in normal
 * operation is serialization failure while encoding the inspection payload for
 * terminal output.
 *
 * @author Brian Faust <brian@cline.sh>
 */
final class ListSettingsCommand extends Command
{
    #[Override()]
    protected $signature = 'settings:list
        {--settings= : Limit to one settings class}
        {--property= : Limit to one property}
        {--target= : Limit to one exact target in type:id or app format}';

    #[Override()]
    protected $description = 'List stored settings rows';

    /**
     * Execute the inspection command.
     *
     * Options are translated into a {@see SettingsQuery}, then forwarded to
     * the settings manager's inspection pipeline. The resulting rows are
     * emitted as pretty-printed JSON so other tooling can consume them
     * predictably.
     *
     * Invalid `--target` input fails before any storage query runs because
     * {@see CommandTargetParser} throws immediately. JSON encoding failures are
     * reported to the console and cause a non-zero exit code.
     */
    public function handle(): int
    {
        $settings = $this->option('settings');
        $property = $this->option('property');
        $target = $this->option('target');
        $query = new SettingsQuery(
            settingsClass: is_string($settings) ? $settings : null,
            property: is_string($property) ? $property : null,
            target: is_string($target) ? CommandTargetParser::parse($target) : null,
        );

        $payload = [];

        foreach (Settings::inspect($query) as $value) {
            $payload[] = $value->toArray();
        }

        try {
            $this->line(json_encode($payload, JSON_PRETTY_PRINT | JSON_THROW_ON_ERROR));
        } catch (JsonException $jsonException) {
            $this->error($jsonException->getMessage());

            return self::FAILURE;
        }

        return self::SUCCESS;
    }
}
