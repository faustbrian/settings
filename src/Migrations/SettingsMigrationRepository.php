<?php declare(strict_types=1);

/**
 * Copyright (C) Brian Faust
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Cline\Settings\Migrations;

use Cline\Settings\Contracts\SettingsMigrationRepositoryInterface;
use Illuminate\Database\ConnectionInterface;
use Illuminate\Database\DatabaseManager;
use Illuminate\Database\Query\Builder;

use function array_filter;
use function array_values;
use function config;
use function is_string;

/**
 * Tracks which settings migrations have already been applied.
 *
 * The repository is intentionally narrow and only persists migration name and
 * batch metadata. Actual settings writes continue to flow through the package
 * manager and repository contracts.
 *
 * @author Brian Faust <brian@cline.sh>
 * @psalm-immutable
 */
final readonly class SettingsMigrationRepository implements SettingsMigrationRepositoryInterface
{
    public function __construct(
        private DatabaseManager $database,
    ) {}

    /**
     * @return array<int, string>
     */
    public function ran(): array
    {
        return array_values(array_filter(
            $this->query()
                ->orderBy('batch')
                ->orderBy('migration')
                ->pluck('migration')
                ->all(),
            is_string(...),
        ));
    }

    public function log(string $migration, int $batch): void
    {
        $this->query()->insert([
            'migration' => $migration,
            'batch' => $batch,
        ]);
    }

    public function delete(string $migration): void
    {
        $this->query()
            ->where('migration', $migration)
            ->delete();
    }

    public function nextBatchNumber(): int
    {
        /** @var null|int $batch */
        $batch = $this->query()->max('batch');

        return ($batch ?? 0) + 1;
    }

    /**
     * @return array<int, string>
     */
    public function last(int $steps = 1): array
    {
        return array_values(array_filter(
            $this->query()
                ->orderByDesc('batch')
                ->orderByDesc('migration')
                ->limit($steps)
                ->pluck('migration')
                ->all(),
            is_string(...),
        ));
    }

    private function connection(): ConnectionInterface
    {
        return $this->database->connection();
    }

    private function query(): Builder
    {
        return $this->connection()->table($this->table());
    }

    private function table(): string
    {
        $table = config('settings.table_names.migrations', 'settings_migrations');

        return is_string($table) ? $table : 'settings_migrations';
    }
}
