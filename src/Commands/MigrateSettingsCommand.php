<?php declare(strict_types=1);

/**
 * Copyright (C) Brian Faust
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Cline\Settings\Commands;

use Cline\Settings\Contracts\SettingsMigrationRunnerInterface;
use Illuminate\Console\Command;
use Override;

use function array_filter;
use function array_values;
use function is_array;
use function is_string;

/**
 * Runs pending settings migrations.
 *
 * Settings migrations evolve stored settings data and are tracked separately
 * from schema migrations.
 *
 * @author Brian Faust <brian@cline.sh>
 */
final class MigrateSettingsCommand extends Command
{
    #[Override()]
    protected $signature = 'settings:migrate
        {--path=* : One or more directories that contain settings migrations}';

    #[Override()]
    protected $description = 'Run pending settings migrations';

    public function __construct(
        private readonly SettingsMigrationRunnerInterface $runner,
    ) {
        parent::__construct();
    }

    public function handle(): int
    {
        $migrations = $this->runner->run($this->paths());

        if ($migrations === []) {
            $this->info('Nothing to migrate.');

            return self::SUCCESS;
        }

        foreach ($migrations as $migration) {
            $this->line($migration);
        }

        $this->info('Settings migrations complete.');

        return self::SUCCESS;
    }

    /**
     * @return array<int, string>
     */
    private function paths(): array
    {
        $paths = $this->option('path');

        if (!is_array($paths)) {
            return [];
        }

        return array_values(array_filter($paths, static fn (mixed $path): bool => is_string($path) && $path !== ''));
    }
}
