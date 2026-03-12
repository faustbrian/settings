<?php declare(strict_types=1);

/**
 * Copyright (C) Brian Faust
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Cline\Settings\Commands;

use Cline\Settings\Facades\Settings;
use Cline\Settings\Support\SettingsSnapshot;
use Illuminate\Console\Command;
use JsonException;
use Override;

use const JSON_THROW_ON_ERROR;

use function count;
use function file_get_contents;
use function is_array;
use function is_string;
use function json_decode;

/**
 * Import a JSON settings snapshot into persistent storage.
 *
 * The command replays a serialized {@see SettingsSnapshot} into the package's
 * repository by delegating to {@see \Cline\Settings\SettingsManager::import()}.
 * Each snapshot entry is treated as an exact stored row rather than a full
 * typed settings object, which makes the command suitable for migrations,
 * environment seeding, and restoring exported snapshots.
 *
 * Imports overwrite existing rows at the same coordinates. The command performs
 * only basic file and JSON validation; definition validation and value encoding
 * errors still come from the manager during import.
 *
 * Import is intentionally repository-oriented rather than object-oriented. The
 * snapshot contains exact stored coordinates, so the command can replay rows
 * for carrier, shipping-method, organization, or app scope without inferring
 * any domain-specific fallback behavior.
 *
 * @author Brian Faust <brian@cline.sh>
 */
final class ImportSettingsCommand extends Command
{
    /**
     * The command signature.
     *
     * @var string
     */
    #[Override()]
    protected $signature = 'settings:import
        {path : The JSON snapshot to import}';

    /**
     * The console command description.
     *
     * @var string
     */
    #[Override()]
    protected $description = 'Import settings from a JSON snapshot';

    /**
     * Execute the command.
     *
     * The file is read eagerly, decoded as JSON, normalized into a
     * {@see SettingsSnapshot}, and then imported through the facade. Read
     * failures, malformed JSON, and non-array top-level payloads are reported as
     * command failures. Repository or definition failures during import are not
     * swallowed here and therefore abort the command.
     *
     * Successful imports report the number of snapshot entries processed, not
     * the number of rows that were newly inserted.
     *
     * @return int Command exit code.
     */
    public function handle(): int
    {
        $path = $this->argument('path');

        if (!is_string($path)) {
            $this->error('The import path must be a string.');

            return self::FAILURE;
        }

        $contents = file_get_contents($path);

        if ($contents === false) {
            $this->error('Unable to read the snapshot file.');

            return self::FAILURE;
        }

        try {
            $data = json_decode($contents, true, 512, JSON_THROW_ON_ERROR);
        } catch (JsonException $jsonException) {
            $this->error($jsonException->getMessage());

            return self::FAILURE;
        }

        if (!is_array($data)) {
            $this->error('The snapshot payload must decode to an array.');

            return self::FAILURE;
        }

        /** @var array<string, mixed> $data */
        $snapshot = SettingsSnapshot::fromArray($data);
        Settings::import($snapshot);

        $this->info('Imported '.count($snapshot->entries).' settings rows.');

        return self::SUCCESS;
    }
}
