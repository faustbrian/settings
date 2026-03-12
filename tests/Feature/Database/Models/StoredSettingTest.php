<?php declare(strict_types=1);

/**
 * Copyright (C) Brian Faust
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

use Cline\Settings\Database\Models\StoredSetting;

describe('stored setting model', function (): void {
    test('uses the configured values table and expected casts', function (): void {
        config()->set('settings.table_names.values', 'custom_settings_values');

        $model = new StoredSetting();

        expect($model->getTable())->toBe('custom_settings_values')
            ->and($model->getCasts())->toMatchArray([
                'value' => 'array',
                'version' => 'integer',
            ]);
    });
});
