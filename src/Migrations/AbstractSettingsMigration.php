<?php declare(strict_types=1);

/**
 * Copyright (C) Brian Faust
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Cline\Settings\Migrations;

use Cline\Settings\Contracts\SettingsMigratorInterface;
use Illuminate\Database\Migrations\Migration;

use function resolve;

/**
 * Base class for settings data migrations.
 *
 * Settings migrations evolve persisted settings rows rather than database
 * schema. They mirror Laravel's migration shape with `up()` and `down()`
 * methods, but expose a dedicated settings migrator for exact owner and
 * boundary scoped writes.
 *
 * @author Brian Faust <brian@cline.sh>
 */
abstract class AbstractSettingsMigration extends Migration
{
    protected SettingsMigratorInterface $migrator;

    public function __construct()
    {
        $this->migrator = resolve(SettingsMigratorInterface::class);
    }

    abstract public function up(): void;

    abstract public function down(): void;
}
