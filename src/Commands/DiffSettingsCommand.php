<?php declare(strict_types=1);

/**
 * Copyright (C) Brian Faust
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Cline\Settings\Commands;

use Cline\Settings\Facades\Settings;
use Cline\Settings\Support\ResolutionChain;
use Illuminate\Console\Command;
use JsonException;
use Override;

use const JSON_PRETTY_PRINT;
use const JSON_THROW_ON_ERROR;

use function array_keys;
use function array_merge;
use function array_unique;
use function collect;
use function get_object_vars;
use function is_string;
use function json_encode;

/**
 * Compare the resolved values for a settings class at two exact targets.
 *
 * Each side is resolved independently with a single-entry
 * {@see ResolutionChain}, so the diff reflects the values stored or defaulted
 * at one exact target rather than a broader fallback stack. This makes the
 * command useful when checking how one scope differs from another before
 * promotion, import, or cleanup.
 *
 * Only properties whose resolved values differ are included in the output. The
 * command serializes the final diff as JSON for use by operators and tooling.
 * Properties omitted from the result should be interpreted as equivalent after
 * exact-target resolution, not as absent from the settings definition.
 *
 * @author Brian Faust <brian@cline.sh>
 */
final class DiffSettingsCommand extends Command
{
    /**
     * The command signature.
     *
     * @var string
     */
    #[Override()]
    protected $signature = 'settings:diff
        {settings : The typed settings class to compare}
        {--left= : The left target in type:id or app format}
        {--right= : The right target in type:id or app format}';

    /**
     * The console command description.
     *
     * @var string
     */
    #[Override()]
    protected $description = 'Diff two exact settings targets';

    /**
     * Execute the command.
     *
     * The command validates the required string inputs, parses both targets,
     * resolves the settings class once per side, and emits a JSON object
     * containing only changed properties. Missing stored values naturally appear
     * as defaults because each side resolves through the normal manager API.
     *
     * Target parsing or settings resolution failures bubble up. JSON encoding
     * failures are converted into a command failure here.
     *
     * The diff is symmetric in structure but not in meaning: `left` and
     * `right` labels are preserved exactly as provided so downstream tooling
     * can treat one side as the proposed source of truth.
     *
     * @return int Command exit code.
     */
    public function handle(): int
    {
        $settingsClass = $this->argument('settings');
        $left = $this->option('left');
        $right = $this->option('right');

        if (!is_string($settingsClass) || !is_string($left) || !is_string($right)) {
            $this->error('The command requires string settings, left, and right targets.');

            return self::FAILURE;
        }

        $leftTarget = CommandTargetParser::parse($left);
        $rightTarget = CommandTargetParser::parse($right);

        $left = get_object_vars(Settings::resolveWithMetadata($settingsClass, null, new ResolutionChain([$leftTarget]))->settings);
        $right = get_object_vars(Settings::resolveWithMetadata($settingsClass, null, new ResolutionChain([$rightTarget]))->settings);

        $diff = collect(array_unique(array_merge(array_keys($left), array_keys($right))))
            ->mapWithKeys(static fn (string $property): array => [$property => [
                'left' => $left[$property] ?? null,
                'right' => $right[$property] ?? null,
            ]])
            ->reject(static fn (array $values): bool => $values['left'] === $values['right'])
            ->all();

        try {
            $this->line(json_encode($diff, JSON_PRETTY_PRINT | JSON_THROW_ON_ERROR));
        } catch (JsonException $jsonException) {
            $this->error($jsonException->getMessage());

            return self::FAILURE;
        }

        return self::SUCCESS;
    }
}
