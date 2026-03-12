<?php declare(strict_types=1);

/**
 * Copyright (C) Brian Faust
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

use Carbon\CarbonImmutable;
use Cline\Settings\Exceptions\SettingsSerializationException;
use Cline\Settings\Facades\Settings;
use Cline\Settings\Support\StoredValue;
use Illuminate\Support\Facades\DB;
use Tests\Fixtures\Models\Carrier;
use Tests\Fixtures\Models\User;
use Tests\Fixtures\Settings\BrokenEncodedSettings;
use Tests\Fixtures\Settings\CarrierCredentialSettings;

describe('settings value codec', function (): void {
    test('hydrates cast properties and decrypts encrypted properties', function (): void {
        $carrier = Carrier::query()->create(['name' => 'DHL']);
        $subject = User::query()->create(['name' => 'Alice']);

        $stored = Settings::for($subject)
            ->ownedBy($carrier)
            ->compareAndSet(
                CarrierCredentialSettings::class,
                'apiToken',
                'top-secret',
            );

        Settings::for($subject)
            ->ownedBy($carrier)
            ->set(
                CarrierCredentialSettings::class,
                'rotatesAt',
                CarbonImmutable::parse('2026-03-10T12:00:00+00:00'),
            );

        $resolved = Settings::for($subject)
            ->ownedBy($carrier)
            ->fallbackToApp()
            ->get(CarrierCredentialSettings::class);

        expect($stored)->toBeInstanceOf(StoredValue::class)
            ->and($stored->version)->toBe(1)
            ->and($resolved->apiToken)->toBe('top-secret')
            ->and($resolved->rotatesAt)->toBeInstanceOf(DateTimeImmutable::class)
            ->and($resolved->rotatesAt->format(\DATE_ATOM))->toBe('2026-03-10T12:00:00+00:00');
    });

    test('stores encrypted payloads without leaving plaintext in the database', function (): void {
        $carrier = Carrier::query()->create(['name' => 'FedEx']);
        $subject = User::query()->create(['name' => 'Bob']);

        Settings::for($subject)->ownedBy($carrier)->set(
            CarrierCredentialSettings::class,
            'apiToken',
            'super-secret',
        );

        $raw = DB::table('settings_values')
            ->where('settings_class', CarrierCredentialSettings::class)
            ->where('property', 'apiToken')
            ->value('value');

        expect($raw)->toBeString()
            ->and($raw)->not->toContain('super-secret');
    });

    test('throws when encrypted serialization cannot encode the payload', function (): void {
        $subject = User::query()->create(['name' => 'Bob']);

        expect(fn (): mixed => Settings::for($subject)->fallbackToApp()->set(
            BrokenEncodedSettings::class,
            'broken',
            "\xB1\x31",
        ))->toThrow(SettingsSerializationException::class);
    });
});
