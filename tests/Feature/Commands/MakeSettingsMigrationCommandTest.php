<?php declare(strict_types=1);

/**
 * Copyright (C) Brian Faust
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\File;

describe('make settings migration command', function (): void {
    test('creates a settings migration stub', function (): void {
        $fixturesDirectory = realpath(__DIR__.'/../../Fixtures');
        assert(is_string($fixturesDirectory));
        $path = $fixturesDirectory.'/generated-settings-migrations';

        File::deleteDirectory($path);

        $exitCode = Artisan::call('make:settings-migration', [
            'name' => 'seed scoped shipment settings',
            '--path' => $path,
        ]);
        expect($exitCode)->toBe(0);

        expect(is_dir($path))->toBeTrue();

        $files = glob($path.'/*.php');
        expect($files)->not->toBeFalse();
        assert(is_array($files));

        expect($files)->toHaveCount(1);

        $contents = file_get_contents($files[0]);
        assert(is_string($contents));

        expect($contents)->toContain('extends SettingsMigration')
            ->and($contents)->toContain('public function up(): void')
            ->and($contents)->toContain('public function down(): void');

        File::deleteDirectory($path);
    });
});
