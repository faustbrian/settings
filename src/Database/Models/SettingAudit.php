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
 * Eloquent model for immutable settings audit rows.
 *
 * Audit records capture before/after payloads and resolution context for
 * repository writes. The model is intentionally thin and delegates table naming
 * and casting concerns to package configuration.
 *
 * @author Brian Faust <brian@cline.sh>
 */
final class SettingAudit extends Model
{
    /** @use HasFactory<Factory<static>> */
    use HasFactory;
    use HasVariablePrimaryKey;

    /**
     * Allow full mass assignment for repository-controlled audit payloads.
     *
     * Audit rows are written internally by the package rather than from
     * end-user input, so guarding individual attributes adds no value here.
     *
     * @var list<string>
     */
    #[Override()]
    protected $guarded = [];

    /**
     * Cast structured audit payload columns into arrays.
     *
     * This keeps context and value snapshots usable without manual decoding
     * when inspecting audit rows through Eloquent.
     *
     * @var array<string, string>
     */
    #[Override()]
    protected $casts = [
        'context' => 'array',
        'new_value' => 'array',
        'old_value' => 'array',
    ];

    /**
     * Resolve the audit table name from package configuration.
     *
     * Falls back to Eloquent's default table naming when the configured value
     * is missing or not a string.
     */
    #[Override()]
    public function getTable(): string
    {
        $table = config('settings.table_names.audits', parent::getTable());

        return is_string($table) ? $table : parent::getTable();
    }
}
