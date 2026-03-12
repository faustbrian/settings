<?php declare(strict_types=1);

/**
 * Copyright (C) Brian Faust
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

use Cline\Settings\Database\Models\SettingAudit;

describe('setting audit model', function (): void {
    test('uses the configured audit table and expected casts', function (): void {
        config()->set('settings.table_names.audits', 'custom_settings_audits');

        $model = new SettingAudit();

        expect($model->getTable())->toBe('custom_settings_audits')
            ->and($model->getCasts())->toMatchArray([
                'context' => 'array',
                'new_value' => 'array',
                'old_value' => 'array',
            ]);
    });
});
