<?php declare(strict_types=1);

/**
 * Copyright (C) Brian Faust
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

use Cline\Settings\Migrations\SettingsMigration;
use Tests\Fixtures\Models\BusinessEntity;
use Tests\Fixtures\Models\Carrier;
use Tests\Fixtures\Models\Organization;
use Tests\Fixtures\Models\ShippingMethod;
use Tests\Fixtures\Models\User;
use Tests\Fixtures\Settings\OrganizationMemberSettings;
use Tests\Fixtures\Settings\ShipmentPricingSettings;

return new class() extends SettingsMigration
{
    public function up(): void
    {
        $businessEntity = BusinessEntity::query()->where('name', 'Finland')->firstOrFail();
        $carrier = Carrier::query()->where('name', 'DHL')->firstOrFail();
        $shippingMethod = ShippingMethod::query()->where('name', 'Express')->firstOrFail();
        $organization = Organization::query()->where('name', 'Acme Logistics')->firstOrFail();
        $user = User::query()->where('name', 'Alice')->firstOrFail();

        $this->migrator->for(ShipmentPricingSettings::class)
            ->ownedBy($businessEntity)
            ->add('callBeforeDeliveryFee', 3.25)
            ->add('returnFreightDocFee', 0.35);

        $this->migrator->for(ShipmentPricingSettings::class)
            ->ownedBy($carrier)
            ->add('callBeforeDeliveryFee', 5.5)
            ->add('returnFreightDocFee', 0.5);

        $this->migrator->for(ShipmentPricingSettings::class)
            ->ownedBy($shippingMethod)
            ->add('callBeforeDeliveryFee', 8.5)
            ->add('returnFreightDocFee', 1.2);

        $this->migrator->for(OrganizationMemberSettings::class)
            ->ownedBy($organization)
            ->add('canUsePrioritySupport', false)
            ->add('dailyShipmentLimit', 25);

        $this->migrator->for(OrganizationMemberSettings::class)
            ->ownedBy($organization, $user)
            ->add('canUsePrioritySupport', true)
            ->add('dailyShipmentLimit', 100);
    }

    public function down(): void
    {
        $businessEntity = BusinessEntity::query()->where('name', 'Finland')->firstOrFail();
        $carrier = Carrier::query()->where('name', 'DHL')->firstOrFail();
        $shippingMethod = ShippingMethod::query()->where('name', 'Express')->firstOrFail();
        $organization = Organization::query()->where('name', 'Acme Logistics')->firstOrFail();
        $user = User::query()->where('name', 'Alice')->firstOrFail();

        $this->migrator->for(ShipmentPricingSettings::class)
            ->ownedBy($shippingMethod)
            ->deleteIfExists('callBeforeDeliveryFee')
            ->deleteIfExists('returnFreightDocFee');

        $this->migrator->for(ShipmentPricingSettings::class)
            ->ownedBy($carrier)
            ->deleteIfExists('callBeforeDeliveryFee')
            ->deleteIfExists('returnFreightDocFee');

        $this->migrator->for(ShipmentPricingSettings::class)
            ->ownedBy($businessEntity)
            ->deleteIfExists('callBeforeDeliveryFee')
            ->deleteIfExists('returnFreightDocFee');

        $this->migrator->for(OrganizationMemberSettings::class)
            ->ownedBy($organization, $user)
            ->deleteIfExists('canUsePrioritySupport')
            ->deleteIfExists('dailyShipmentLimit');

        $this->migrator->for(OrganizationMemberSettings::class)
            ->ownedBy($organization)
            ->deleteIfExists('canUsePrioritySupport')
            ->deleteIfExists('dailyShipmentLimit');
    }
};
