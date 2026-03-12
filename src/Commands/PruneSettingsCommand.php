<?php declare(strict_types=1);

/**
 * Copyright (C) Brian Faust
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Cline\Settings\Commands;

use Carbon\CarbonImmutable;
use Cline\Settings\Facades\Settings;
use Cline\Settings\Support\SettingsQuery;
use DateInterval;
use Illuminate\Console\Command;
use Override;

use function is_string;

/**
 * Delete stale persisted settings rows from the repository.
 *
 * This command is a maintenance entry point around
 * {@see \Cline\Settings\SettingsManager::prune()}. It operates at the storage
 * row level, not the typed settings object level, so removing a row only causes
 * future resolutions to fall back to lower-precedence stored values or class
 * defaults.
 *
 * Pruning is based on the repository's `updated_at` semantics. The optional
 * `--settings` filter narrows the operation to one settings class, while
 * `--days` controls the age threshold relative to the current clock.
 *
 * Because this command deletes persisted rows directly, operators should treat
 * it as an irreversible maintenance action even though the package does not
 * expose destructive shell-level behavior.
 *
 * @author Brian Faust <brian@cline.sh>
 */
final class PruneSettingsCommand extends Command
{
    /**
     * The command signature.
     *
     * @var string
     */
    #[Override()]
    protected $signature = 'settings:prune
        {--settings= : Limit pruning to one settings class}
        {--days=30 : Remove rows not updated in this many days or more}';

    /**
     * The console command description.
     *
     * @var string
     */
    #[Override()]
    protected $description = 'Prune stale persisted settings rows';

    /**
     * Execute the command.
     *
     * The command computes a cutoff timestamp by subtracting the requested day
     * count from the current time, builds a repository query, and delegates the
     * deletion to the settings manager. Negative or zero values are passed
     * through as-is, which means the resulting cutoff may prune aggressively;
     * this command intentionally leaves that policy decision to the operator.
     *
     * The returned count is whatever the repository reports as removed. The
     * command does not attempt to re-read or summarize which targets changed.
     *
     * @return int Command exit code.
     */
    public function handle(): int
    {
        $days = (int) $this->option('days');
        $before = CarbonImmutable::now()->sub(
            new DateInterval('P'.$days.'D'),
        );
        $settings = $this->option('settings');
        $query = new SettingsQuery(
            settingsClass: is_string($settings) ? $settings : null,
        );

        $removed = Settings::prune($before, $query);

        $this->info($removed.' stale rows removed');

        return self::SUCCESS;
    }
}
