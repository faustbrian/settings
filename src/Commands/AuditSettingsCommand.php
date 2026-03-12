<?php declare(strict_types=1);

/**
 * Copyright (C) Brian Faust
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Cline\Settings\Commands;

use Cline\Settings\Facades\Settings;
use Cline\Settings\Support\SettingsAuditEntry;
use Cline\Settings\Support\SettingsAuditQuery;
use Illuminate\Console\Command;
use JsonException;
use Override;

use const JSON_PRETTY_PRINT;
use const JSON_THROW_ON_ERROR;

use function array_map;
use function is_string;
use function json_encode;

/**
 * @author Brian Faust <brian@cline.sh>
 */
final class AuditSettingsCommand extends Command
{
    #[Override()]
    protected $signature = 'settings:audit
        {--action= : Limit to one audit action}
        {--settings= : Limit to one settings class}
        {--property= : Limit to one property}
        {--target= : Limit to one exact target in type:id or app format}';

    #[Override()]
    protected $description = 'List settings audit rows';

    public function handle(): int
    {
        $action = $this->option('action');
        $settings = $this->option('settings');
        $property = $this->option('property');
        $target = $this->option('target');

        $entries = Settings::audit(
            new SettingsAuditQuery(
                action: is_string($action) ? $action : null,
                settingsClass: is_string($settings) ? $settings : null,
                property: is_string($property) ? $property : null,
                target: is_string($target) ? CommandTargetParser::parse($target) : null,
            ),
        );

        try {
            $this->line(json_encode(
                array_map(static fn (SettingsAuditEntry $entry): array => $entry->toArray(), $entries),
                JSON_PRETTY_PRINT | JSON_THROW_ON_ERROR,
            ));
        } catch (JsonException $jsonException) {
            $this->error($jsonException->getMessage());

            return self::FAILURE;
        }

        return self::SUCCESS;
    }
}
