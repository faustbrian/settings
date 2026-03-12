<?php declare(strict_types=1);

/**
 * Copyright (C) Brian Faust
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Cline\Settings\Database\Models;

use Cline\VariableKeys\Database\Concerns\HasVariablePrimaryKey;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Override;

use function config;
use function is_string;

/**
 * Eloquent record for one persisted settings-property override.
 *
 * The settings system stores values at property granularity rather than as a
 * serialized blob for an entire settings class. Each row therefore represents
 * a single override for one property in one settings namespace at one exact
 * resolution target.
 *
 * The owner and boundary columns form the persisted coordinates used by the
 * repository during resolution. They are not interpreted by the model itself;
 * precedence and fallback are handled by the resolution layer that decides
 * which coordinates to query and in what order.
 *
 * Runtime writes are expected to come through the repository so versioning,
 * locking, and normalization rules stay centralized. The model's role is the
 * persistence boundary for the configured storage table.
 *
 * @author Brian Faust <brian@cline.sh>
 */
final class StoredSetting extends Model
{
    /** @use HasFactory<Factory<static>> */
    use HasFactory;
    use HasVariablePrimaryKey;

    /**
     * The package writes explicit attributes from the repository and does not
     * rely on per-column mass-assignment allow lists.
     *
     * @var list<string>
     */
    #[Override()]
    protected $guarded = [];

    /**
     * Persist payloads as structured arrays and version counters as integers.
     *
     * The `value` column intentionally stores an encoded payload rather than a
     * scalar so higher layers can preserve serializer metadata alongside the
     * resolved value when needed.
     *
     * @var array<string, string>
     */
    #[Override()]
    protected $casts = [
        'value' => 'array',
        'version' => 'integer',
    ];

    /**
     * Resolve the backing table name from package configuration at runtime.
     *
     * This keeps the model aligned with consumer-published configuration and
     * allows the repository to instantiate a custom model class while still
     * respecting package table overrides. If the configured value is invalid,
     * the parent model table name is used as a safe fallback.
     */
    #[Override()]
    public function getTable(): string
    {
        $table = config('settings.table_names.values', parent::getTable());

        return is_string($table) ? $table : parent::getTable();
    }
}
