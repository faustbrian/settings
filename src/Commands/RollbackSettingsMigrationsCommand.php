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
use function is_int;
use function is_string;

/**
 * Rolls back previously run settings migrations.
 *
 * @author Brian Faust <brian@cline.sh>
 */
final class RollbackSettingsMigrationsCommand extends Command
{
    #[Override()]
    protected $signature = 'settings:migrate-rollback
        {--step=1 : Number of settings migrations to roll back}
        {--path=* : One or more directories that contain settings migrations}';

    #[Override()]
    protected $description = 'Roll back settings migrations';

    public function __construct(
        private readonly SettingsMigrationRunnerInterface $runner,
    ) {
        parent::__construct();
    }

    public function handle(): int
    {
        $rolledBack = $this->runner->rollback($this->steps(), $this->paths());

        if ($rolledBack === []) {
            $this->info('Nothing to rollback.');

            return self::SUCCESS;
        }

        foreach ($rolledBack as $migration) {
            $this->line($migration);
        }

        $this->info('Settings migration rollback complete.');

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

    private function steps(): int
    {
        $steps = $this->option('step');

        return is_int($steps) ? $steps : 1;
    }
}
