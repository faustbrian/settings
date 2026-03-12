<?php declare(strict_types=1);

/**
 * Copyright (C) Brian Faust
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Cline\Settings\Commands;

use Cline\Settings\Facades\Settings;
use Illuminate\Console\Command;
use Override;

use function is_numeric;

/**
 * @author Brian Faust <brian@cline.sh>
 */
final class RollbackSettingsCommand extends Command
{
    #[Override()]
    protected $signature = 'settings:rollback
        {audit : The audit row id to roll back}';

    #[Override()]
    protected $description = 'Roll back one settings audit row';

    public function handle(): int
    {
        $audit = $this->argument('audit');

        if (!is_numeric($audit)) {
            $this->error('The audit argument must be numeric.');

            return self::FAILURE;
        }

        Settings::rollback((int) $audit);

        $this->info('Rolled back audit row '.$audit);

        return self::SUCCESS;
    }
}
