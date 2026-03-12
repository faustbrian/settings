<?php declare(strict_types=1);

/**
 * Copyright (C) Brian Faust
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

use Cline\Settings\Exceptions\MissingSettingsValueException;
use Cline\Settings\Facades\Settings;
use Cline\Settings\Support\Reference;
use Cline\Settings\Support\ResolutionTarget;
use Tests\Fixtures\Models\BusinessEntity;
use Tests\Fixtures\Models\Carrier;
use Tests\Fixtures\Models\Organization;
use Tests\Fixtures\Models\ShippingMethod;
use Tests\Fixtures\Models\User;
use Tests\Fixtures\Settings\OrganizationMemberSettings;
use Tests\Fixtures\Settings\RequiredSettings;
use Tests\Fixtures\Settings\ShipmentPricingSettings;

describe('resolution chain', function (): void {
    test('resolves typed settings using explicit owner fallback order', function (): void {
        $carrier = Carrier::query()->create(['name' => 'FedEx']);
        $shippingMethod = ShippingMethod::query()->create([
            'carrier_id' => $carrier->getKey(),
            'name' => 'Priority Overnight',
        ]);
        $subject = User::query()->create(['name' => 'Alice']);

        Settings::for($subject)->ownedBy($carrier)->save(
            new ShipmentPricingSettings(
                callBeforeDeliveryFee: 5.5,
                returnFreightDocFee: 0.5,
            ),
        );

        Settings::for($subject)->ownedBy($shippingMethod)->save(
            new ShipmentPricingSettings(
                callBeforeDeliveryFee: 8.5,
                returnFreightDocFee: 1.2,
            ),
        );

        $resolved = Settings::for($subject)
            ->ownedBy($shippingMethod)
            ->fallbackTo($carrier)
            ->fallbackToApp()
            ->get(ShipmentPricingSettings::class);

        expect($resolved->callBeforeDeliveryFee)->toBe(8.5)
            ->and($resolved->returnFreightDocFee)->toBe(1.2);
    });

    test('supports business entity as an explicit fallback layer', function (): void {
        $businessEntity = BusinessEntity::query()->create([
            'name' => 'Finland',
            'country_code' => 'FI',
        ]);
        $carrier = Carrier::query()->create(['name' => 'PostNord']);
        $shippingMethod = ShippingMethod::query()->create([
            'carrier_id' => $carrier->getKey(),
            'name' => 'Parcel',
        ]);
        $subject = User::query()->create(['name' => 'Alice']);

        Settings::for($subject)->ownedBy($businessEntity)->save(
            new ShipmentPricingSettings(
                callBeforeDeliveryFee: 3.25,
                returnFreightDocFee: 0.35,
            ),
        );

        $resolved = Settings::for($subject)
            ->ownedBy($shippingMethod)
            ->fallbackTo($carrier)
            ->fallbackTo($businessEntity)
            ->fallbackToApp()
            ->get(ShipmentPricingSettings::class);

        expect($resolved->callBeforeDeliveryFee)->toBe(3.25)
            ->and($resolved->returnFreightDocFee)->toBe(0.35);
    });

    test('exposes per-property provenance metadata for resolved settings', function (): void {
        $businessEntity = BusinessEntity::query()->create([
            'name' => 'Sweden',
            'country_code' => 'SE',
        ]);
        $carrier = Carrier::query()->create(['name' => 'DHL']);
        $subject = User::query()->create(['name' => 'Frank']);

        Settings::for($subject)->ownedBy($businessEntity)->set(
            ShipmentPricingSettings::class,
            'returnFreightDocFee',
            1.75,
        );

        Settings::for($subject)->ownedBy($carrier)->set(
            ShipmentPricingSettings::class,
            'callBeforeDeliveryFee',
            9.25,
        );

        $resolved = Settings::for($subject)
            ->ownedBy($carrier)
            ->fallbackTo($businessEntity)
            ->fallbackToApp()
            ->getResolved(ShipmentPricingSettings::class);

        expect($resolved->settings->callBeforeDeliveryFee)->toBe(9.25)
            ->and($resolved->settings->returnFreightDocFee)->toBe(1.75)
            ->and($resolved->usesDefault('callBeforeDeliveryFee'))->toBeFalse()
            ->and($resolved->usesDefault('returnFreightDocFee'))->toBeFalse()
            ->and($resolved->sourceFor('callBeforeDeliveryFee')?->ownerType())->toBe('carrier')
            ->and($resolved->sourceFor('returnFreightDocFee')?->ownerType())->toBe('business_entity');
    });

    test('resolves boundary-specific settings before owner-wide settings', function (): void {
        $organization = Organization::query()->create(['name' => 'Acme Logistics']);
        $user = User::query()->create(['name' => 'Bob']);

        Settings::for($user)->ownedBy($organization)->save(
            new OrganizationMemberSettings(
                canUsePrioritySupport: false,
                dailyShipmentLimit: 25,
            ),
        );

        Settings::for($user)->ownedBy($organization, $user)->save(
            new OrganizationMemberSettings(
                canUsePrioritySupport: true,
                dailyShipmentLimit: 100,
            ),
        );

        $resolved = Settings::for($user)
            ->ownedBy($organization, $user)
            ->fallbackTo($organization)
            ->fallbackToApp()
            ->get(OrganizationMemberSettings::class);

        expect($resolved->canUsePrioritySupport)->toBeTrue()
            ->and($resolved->dailyShipmentLimit)->toBe(100);
    });

    test('falls back to explicit settings defaults when no override exists in the chain', function (): void {
        $subject = User::query()->create(['name' => 'Charlie']);

        $resolved = Settings::for($subject)
            ->fallbackToApp()
            ->get(ShipmentPricingSettings::class);

        expect($resolved->callBeforeDeliveryFee)->toBe(0.0)
            ->and($resolved->returnFreightDocFee)->toBe(0.1);
    });

    test('does not apply implicit fallbacks outside the explicit chain', function (): void {
        $carrier = Carrier::query()->create(['name' => 'DHL']);
        $shippingMethod = ShippingMethod::query()->create([
            'carrier_id' => $carrier->getKey(),
            'name' => 'Express',
        ]);
        $subject = User::query()->create(['name' => 'Dana']);

        Settings::for($subject)->ownedBy($carrier)->save(
            new ShipmentPricingSettings(
                callBeforeDeliveryFee: 4.4,
                returnFreightDocFee: 0.9,
            ),
        );

        $resolved = Settings::for($subject)
            ->ownedBy($shippingMethod)
            ->fallbackToApp()
            ->get(ShipmentPricingSettings::class);

        expect($resolved->callBeforeDeliveryFee)->toBe(0.0)
            ->and($resolved->returnFreightDocFee)->toBe(0.1);
    });

    test('supports lower-level property access for adapter-style consumers', function (): void {
        $businessEntity = BusinessEntity::query()->create([
            'name' => 'Estonia',
            'country_code' => 'EE',
        ]);
        $subject = User::query()->create(['name' => 'Greta']);

        Settings::for($subject)->ownedBy($businessEntity)->set(
            ShipmentPricingSettings::class,
            'callBeforeDeliveryFee',
            6.5,
        );

        $resolved = Settings::for($subject)
            ->fallbackTo($businessEntity)
            ->fallbackToApp()
            ->value(ShipmentPricingSettings::class, 'callBeforeDeliveryFee');

        expect($resolved)->toBe(6.5);
    });

    test('supports scalar owners and explicit null boundaries', function (): void {
        $subject = User::query()->create(['name' => 'Alice']);

        Settings::for($subject)->ownedBy('business-entity-fi')->set(
            ShipmentPricingSettings::class,
            'callBeforeDeliveryFee',
            7.25,
        );

        $value = Settings::for($subject)
            ->ownedBy(
                new ResolutionTarget(Reference::from('business-entity-fi'))->owner,
            )
            ->value(ShipmentPricingSettings::class, 'callBeforeDeliveryFee');

        expect($value)->toBe(7.25);
    });

    test('can forget a scoped property and fall back to the next owner', function (): void {
        $businessEntity = BusinessEntity::query()->create([
            'name' => 'Finland',
            'country_code' => 'FI',
        ]);
        $carrier = Carrier::query()->create(['name' => 'UPS']);
        $subject = User::query()->create(['name' => 'Helmi']);

        Settings::for($subject)->ownedBy($businessEntity)->set(
            ShipmentPricingSettings::class,
            'callBeforeDeliveryFee',
            3.5,
        );

        Settings::for($subject)->ownedBy($carrier)->set(
            ShipmentPricingSettings::class,
            'callBeforeDeliveryFee',
            8.0,
        );

        Settings::for($subject)->ownedBy($carrier)->forget(
            ShipmentPricingSettings::class,
            'callBeforeDeliveryFee',
        );

        $resolved = Settings::for($subject)
            ->ownedBy($carrier)
            ->fallbackTo($businessEntity)
            ->fallbackToApp()
            ->value(ShipmentPricingSettings::class, 'callBeforeDeliveryFee');

        expect($resolved)->toBe(3.5);
    });

    test('can forget all scoped values for a target', function (): void {
        $organization = Organization::query()->create(['name' => 'Acme Logistics']);
        $user = User::query()->create(['name' => 'Iida']);

        Settings::for($user)->ownedBy($organization, $user)->save(
            new OrganizationMemberSettings(
                canUsePrioritySupport: true,
                dailyShipmentLimit: 50,
            ),
        );

        Settings::for($user)->ownedBy($organization, $user)->forget(
            OrganizationMemberSettings::class,
        );

        $resolved = Settings::for($user)
            ->ownedBy($organization, $user)
            ->fallbackTo($organization)
            ->fallbackToApp()
            ->get(OrganizationMemberSettings::class);

        expect($resolved->canUsePrioritySupport)->toBeFalse()
            ->and($resolved->dailyShipmentLimit)->toBe(10);
    });

    test('throws when a required typed property cannot be resolved', function (): void {
        $subject = User::query()->create(['name' => 'Erin']);

        expect(fn (): object => Settings::for($subject)
            ->fallbackToApp()
            ->get(RequiredSettings::class))
            ->toThrow(MissingSettingsValueException::class);
    });
});
