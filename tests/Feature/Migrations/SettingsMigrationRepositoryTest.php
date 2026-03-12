<?php declare(strict_types=1);

/**
 * Copyright (C) Brian Faust
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

use Cline\Settings\Migrations\SettingsMigrationRepository;

describe('settings migration repository', function (): void {
    test('tracks ran migrations batches and deletion', function (): void {
        $repository = new SettingsMigrationRepository($this->app['db']);

        expect($repository->nextBatchNumber())->toBe(1);

        $repository->log('2026_03_12_000000_first', 1);
        $repository->log('2026_03_12_000001_second', 2);

        expect($repository->ran())->toBe([
            '2026_03_12_000000_first',
            '2026_03_12_000001_second',
        ])
            ->and($repository->last())->toBe(['2026_03_12_000001_second'])
            ->and($repository->last(2))->toBe([
                '2026_03_12_000001_second',
                '2026_03_12_000000_first',
            ])
            ->and($repository->nextBatchNumber())->toBe(3);

        $repository->delete('2026_03_12_000001_second');

        expect($repository->ran())->toBe(['2026_03_12_000000_first']);
    });
});
