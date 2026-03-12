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
use Cline\Settings\Support\ResolutionTarget;
use Illuminate\Console\Command;
use JsonException;
use Override;

use const JSON_PRETTY_PRINT;
use const JSON_THROW_ON_ERROR;

use function get_object_vars;
use function is_array;
use function is_string;
use function json_encode;

/**
 * Resolve a typed settings class through an explicit precedence chain.
 *
 * This command is an operational mirror of {@see \Cline\Settings\SettingsManager::resolveWithMetadata()}.
 * It accepts exact targets rather than inferring context, making it useful for
 * debugging how stored overrides and defaults combine for a given settings
 * class.
 *
 * `--target` entries are treated as higher-priority resolution steps and are
 * evaluated before any `--fallback` entries. If neither option is supplied the
 * command resolves against the application-wide target only. The output is the
 * hydrated object's public properties rendered as JSON, not the richer
 * provenance metadata returned by the underlying manager API.
 *
 * This command never mutates stored settings. It exists to make the package's
 * explicit precedence rules observable from the CLI without forcing operators
 * to inspect raw repository rows by hand.
 *
 * @author Brian Faust <brian@cline.sh>
 */
final class ResolveSettingsCommand extends Command
{
    /**
     * The command signature.
     *
     * `--target` and `--fallback` may be provided multiple times to build an
     * explicit first-match-wins resolution chain.
     *
     * @var string
     */
    #[Override()]
    protected $signature = 'settings:resolve
        {settings : The typed settings class to resolve}
        {--target=* : High-priority exact target(s) in type:id or app format}
        {--fallback=* : Lower-priority exact target(s) in type:id or app format}';

    /**
     * The console command description.
     *
     * @var string
     */
    #[Override()]
    protected $description = 'Resolve a typed settings class through an explicit chain';

    /**
     * Execute the command.
     *
     * The command validates the class-string argument and target options,
     * constructs a {@see ResolutionChain}, resolves the settings object, and
     * prints the hydrated public properties as formatted JSON. Resolution
     * exceptions from the manager and target parsing failures bubble up through
     * Laravel's command handling. JSON encoding failures are reported as command
     * failures here. This keeps the command strict about malformed output while
     * still surfacing package-level resolution errors unchanged.
     *
     * If resolution falls through every stored target, the command still
     * succeeds as long as the settings class can be hydrated from its defaults.
     *
     * @return int Command exit code.
     */
    public function handle(): int
    {
        $settingsClass = $this->argument('settings');
        $targetOptions = $this->option('target');
        $fallbackOptions = $this->option('fallback');

        if (!is_string($settingsClass)) {
            $this->error('The settings argument must be a class string.');

            return self::FAILURE;
        }

        $targets = [
            ...$this->parseTargets(is_array($targetOptions) ? $targetOptions : []),
            ...$this->parseTargets(is_array($fallbackOptions) ? $fallbackOptions : []),
        ];

        if ($targets === []) {
            $targets = [CommandTargetParser::parse('app')];
        }

        $resolved = Settings::resolveWithMetadata(
            $settingsClass,
            null,
            new ResolutionChain($targets),
        );

        try {
            $this->line(json_encode(get_object_vars($resolved->settings), JSON_PRETTY_PRINT | JSON_THROW_ON_ERROR));
        } catch (JsonException $jsonException) {
            $this->error($jsonException->getMessage());

            return self::FAILURE;
        }

        return self::SUCCESS;
    }

    /**
     * @param array<mixed> $values
     *
     * @return array<int, ResolutionTarget>
     *
     * Parse string command options into exact resolution targets.
     *
     * Non-string values are ignored defensively so malformed option arrays do
     * not create partial target objects. Target syntax validation is delegated
     * to {@see CommandTargetParser::parse()}, which is the single source of
     * truth for accepted CLI target grammar.
     */
    private function parseTargets(array $values): array
    {
        $targets = [];

        foreach ($values as $value) {
            if (!is_string($value)) {
                continue;
            }

            $targets[] = CommandTargetParser::parse($value);
        }

        return $targets;
    }
}
