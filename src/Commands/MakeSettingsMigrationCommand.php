<?php declare(strict_types=1);

/**
 * Copyright (C) Brian Faust
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Cline\Settings\Commands;

use Illuminate\Console\Command;
use Illuminate\Filesystem\Filesystem;
use Illuminate\Support\Facades\Date;
use Override;

use function config;
use function database_path;
use function is_array;
use function is_string;
use function mb_trim;
use function preg_replace;
use function sprintf;

/**
 * Generates a new settings migration stub.
 *
 * The generated file follows the package's anonymous-class migration style so
 * settings data changes can be tracked and rolled back just like schema
 * migrations.
 *
 * @author Brian Faust <brian@cline.sh>
 */
final class MakeSettingsMigrationCommand extends Command
{
    #[Override()]
    protected $signature = 'make:settings-migration
        {name : The migration name}
        {--path= : Directory to write the migration file into}';

    #[Override()]
    protected $description = 'Create a new settings migration';

    public function __construct(
        private readonly Filesystem $files,
    ) {
        parent::__construct();
    }

    public function handle(): int
    {
        $name = $this->argument('name');
        $path = $this->option('path');

        if (!is_string($name)) {
            $this->error('The migration name must be a string.');

            return self::FAILURE;
        }

        $directory = is_string($path) ? $path : $this->defaultPath();
        $filename = sprintf(
            '%s_%s.php',
            Date::now()->format('Y_m_d_His'),
            mb_trim((string) preg_replace('/[^a-z0-9]+/i', '_', $name), '_'),
        );

        $this->files->ensureDirectoryExists($directory);
        $this->files->put($directory.'/'.$filename, $this->stub());

        $this->info($directory.'/'.$filename);

        return self::SUCCESS;
    }

    private function defaultPath(): string
    {
        $paths = config('settings.migrations.paths', []);

        if (!is_array($paths)) {
            return database_path('settings');
        }

        $firstPath = $paths[0] ?? database_path('settings');

        return is_string($firstPath) ? $firstPath : database_path('settings');
    }

    private function stub(): string
    {
        return <<<'PHP'
<?php declare(strict_types=1);

/**
 * Copyright (C) Brian Faust
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

use Cline\Settings\Migrations\AbstractSettingsMigration;

return new class extends AbstractSettingsMigration
{
    public function up(): void
    {
        //
    }

    public function down(): void
    {
        //
    }
};
PHP;
    }
}
