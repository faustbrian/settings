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
use Override;

use function count;
use function file_put_contents;
use function is_string;

/**
 * Exports persisted settings rows into a portable snapshot document.
 *
 * The export command materializes a
 * {@see \Cline\Settings\Support\SettingsSnapshot} from the current storage
 * contents and writes it to disk as JSON. It is the package's boundary for
 * backups, fixture generation, and moving stored values between environments
 * without coupling callers to database tables.
 *
 * Export uses the same filtering semantics as inspection, but unlike
 * `settings:list` it has an explicit filesystem side effect: the destination
 * file is overwritten with the newly encoded snapshot payload.
 *
 * @author Brian Faust <brian@cline.sh>
 */
final class ExportSettingsCommand extends Command
{
    #[Override()]
    protected $signature = 'settings:export
        {path : The JSON file to write}
        {--settings= : Limit export to one settings class}
        {--target= : Limit export to one exact target in type:id or app format}';

    #[Override()]
    protected $description = 'Export stored settings to a JSON snapshot';

    /**
     * Build and write a snapshot file for the selected settings rows.
     *
     * The command validates the path argument shape, converts CLI filters into
     * a {@see SettingsQuery}, exports the matching rows, and writes the JSON
     * snapshot to the requested location. The storage layer is not mutated, but
     * the destination file is created or replaced.
     *
     * Invalid `--target` input fails before export begins. A non-string `path`
     * argument is treated as a command usage error and returns failure.
     */
    public function handle(): int
    {
        $settings = $this->option('settings');
        $target = $this->option('target');
        $path = $this->argument('path');

        if (!is_string($path)) {
            $this->error('The export path must be a string.');

            return self::FAILURE;
        }

        $query = new SettingsQuery(
            settingsClass: is_string($settings) ? $settings : null,
            target: is_string($target) ? CommandTargetParser::parse($target) : null,
        );

        $snapshot = Settings::export($query);
        file_put_contents($path, $snapshot->toJson());

        $this->info('Exported '.count($snapshot->entries).' settings rows.');

        return self::SUCCESS;
    }
}
