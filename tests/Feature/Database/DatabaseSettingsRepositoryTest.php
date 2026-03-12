<?php declare(strict_types=1);

/**
 * Copyright (C) Brian Faust
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

use Cline\Settings\Database\DatabaseSettingsRepository;
use Cline\Settings\Exceptions\ConcurrentSettingsWriteException;
use Cline\Settings\Support\ResolutionTarget;
use Cline\Settings\Support\SettingsQuery;

describe('database settings repository', function (): void {
    test('saves finds lists deletes purges and prunes exact target rows', function (): void {
        $repository = new DatabaseSettingsRepository();
        $target = ResolutionTarget::app();

        $saved = $repository->save(
            'SettingsClass',
            'settings.namespace',
            'apiToken',
            ['data' => 'secret', 'cast' => null, 'encrypted' => false],
            $target,
        );

        expect($saved->version)->toBe(1)
            ->and($repository->find('SettingsClass', 'settings.namespace', 'apiToken', $target))
            ->not->toBeNull()
            ->and($repository->all(
                new SettingsQuery(settingsClass: 'SettingsClass'),
            ))
            ->toHaveCount(1);

        expect($repository->delete('SettingsClass', 'settings.namespace', 'apiToken', $target))->toBeTrue();

        $repository->save(
            'SettingsClass',
            'settings.namespace',
            'apiToken',
            ['data' => 'secret', 'cast' => null, 'encrypted' => false],
            $target,
        );
        $repository->save(
            'SettingsClass',
            'settings.namespace',
            'enabled',
            ['data' => true, 'cast' => null, 'encrypted' => false],
            $target,
        );

        expect($repository->purge('SettingsClass', 'settings.namespace', $target))->toBe(2);
    });

    test('rejects stale compare and set writes', function (): void {
        $repository = new DatabaseSettingsRepository();
        $target = ResolutionTarget::app();

        $saved = $repository->save(
            'SettingsClass',
            'settings.namespace',
            'apiToken',
            ['data' => 'secret', 'cast' => null, 'encrypted' => false],
            $target,
        );

        expect(fn (): mixed => $repository->save(
            'SettingsClass',
            'settings.namespace',
            'apiToken',
            ['data' => 'changed', 'cast' => null, 'encrypted' => false],
            $target,
            $saved->version + 1,
        ))->toThrow(ConcurrentSettingsWriteException::class);
    });
});
