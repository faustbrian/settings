<?php declare(strict_types=1);

/**
 * Copyright (C) Brian Faust
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

use Carbon\CarbonImmutable;
use Cline\Settings\Support\Reference;
use Cline\Settings\Support\ResolutionTarget;
use Cline\Settings\Support\SettingsAuditLogger;
use Illuminate\Support\Facades\DB;
use Tests\Fixtures\Models\Carrier;
use Tests\Fixtures\Models\User;

describe('settings audit logger', function (): void {
    test('persists normalized audit rows for mutations', function (): void {
        $carrier = Carrier::query()->create(['name' => 'DHL']);
        $user = User::query()->create(['name' => 'Alice']);

        $logger = new SettingsAuditLogger();
        $logger->log(
            'saved',
            'SettingsClass',
            'settings.namespace',
            'rotatesAt',
            new ResolutionTarget(Reference::from($carrier)),
            $user,
            CarbonImmutable::parse('2026-03-12T00:00:00+00:00'),
            ['status' => true],
        );

        $row = DB::table('settings_audits')->first();

        expect($row)->not->toBeNull()
            ->and($row->action)->toBe('saved')
            ->and($row->owner_type)->toBe('carrier')
            ->and($row->subject_type)->toBe('user');
    });
});
