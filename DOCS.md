# Settings Package Documentation

## Overview

`cline/settings` is a typed Laravel settings package for applications that
need explicit ownership, explicit scope, and explicit fallback order.

The package is built around:

1. typed settings classes
2. exact owner references
3. optional boundary references
4. explicit resolution chains
5. database-backed persistence
6. auditability and operational tooling

It is designed for domains where settings can belong to more than just the
application itself, for example:

- app-wide defaults
- business-entity defaults
- carrier credentials and configuration
- shipping-method overrides
- organization-owned settings
- organization-owned settings that apply only to one user

The package does not invent fallback rules for you. If your application wants
`shipping method -> carrier -> business entity -> app`, you declare that exact
chain at runtime.

## Installation

Install the package:

```bash
composer require cline/settings
```

Publish the package assets if you want local copies of the config and
migration:

```bash
php artisan vendor:publish --provider="Cline\Settings\SettingsServiceProvider"
```

Run migrations:

```bash
php artisan migrate
```

## Configuration

The published config looks like this:

```php
return [
    'primary_key_type' => env('SETTINGS_PRIMARY_KEY_TYPE', 'id'),

    'owner_morph_type' => env('SETTINGS_OWNER_MORPH_TYPE', 'string'),
    'boundary_morph_type' => env('SETTINGS_BOUNDARY_MORPH_TYPE', 'string'),
    'subject_morph_type' => env('SETTINGS_SUBJECT_MORPH_TYPE', 'string'),

    'models' => [
        'setting_audit' => \Cline\Settings\Database\Models\SettingAudit::class,
        'stored_setting' => \Cline\Settings\Database\Models\StoredSetting::class,
    ],

    'table_names' => [
        'audits' => 'settings_audits',
        'values' => 'settings_values',
    ],

    'morphKeyMap' => [],
    'enforceMorphKeyMap' => [],
];
```

### Models

`models.setting_audit` and `models.stored_setting` let you replace the package
Eloquent models.

### Table names

`table_names.audits` and `table_names.values` let you rename the underlying
tables.

### Morph key map

Owner and boundary references are persisted as `type + id`, so morph aliases
matter.

Example:

```php
return [
    'morphKeyMap' => [
        \App\Models\BusinessEntity::class => 'id',
        \App\Models\Carrier::class => 'id',
        \App\Models\ShippingMethod::class => 'id',
        \App\Models\Organization::class => 'uuid',
        \App\Models\User::class => 'id',
    ],
];
```

### Enforced morph key maps

If `enforceMorphKeyMap` is configured, unmapped models are rejected instead of
falling back to their default key column.

## Storage Model

The package stores one row per property per exact target.

The value table includes:

- `settings_class`
- `settings_namespace`
- `property`
- `owner_type`
- `owner_id`
- `boundary_type`
- `boundary_id`
- `value`
- `version`
- timestamps

The unique scope is:

- settings class
- property
- owner type/id
- boundary type/id

That means:

- one typed settings object becomes multiple persisted rows
- versioning is property-scoped
- compare-and-set is property-scoped
- full-object saves still run transactionally

The audit table stores:

- action
- settings class and namespace
- property
- exact target
- subject
- old value
- new value
- timestamp

## Core Concepts

### Settings class

A settings class extends `Cline\Settings\Settings` and exposes constructor-
promoted typed properties. Defaults are declared explicitly through
`defaults()` or `defaultsFor(...)`, not through constructor signatures.

```php
<?php

namespace App\Settings;

use Cline\Settings\Settings;
use Override;

final class ShipmentPricingSettings extends Settings
{
    public function __construct(
        public float $callBeforeDeliveryFee,
        public float $returnFreightDocFee,
    ) {}

    public static function defaults(): array
    {
        return [
            'callBeforeDeliveryFee' => 0.0,
            'returnFreightDocFee' => 0.1,
        ];
    }

    #[Override()]
    public static function namespace(): string
    {
        return 'shipment.pricing';
    }
}
```

Rules:

- every constructor-promoted property is part of the persisted contract
- `defaults()` provides context-free fallbacks
- `defaultsFor(...)` provides subject/chain-aware fallbacks
- missing required properties with no stored value or explicit default are
  treated as resolution errors
- hydration is constructor-based through `cline/struct`

### Subject

The `subject` is the actor or runtime context performing the operation.

The package does not use the subject to decide precedence. It is recorded in
events and audit rows.

Typical subjects:

- the authenticated user
- a system user
- a job actor
- `null` for batch operations

### Owner

An owner is who the setting belongs to.

Examples:

- app scope
- a `BusinessEntity`
- a `Carrier`
- a `ShippingMethod`
- an `Organization`
- a membership model

### Boundary

A boundary narrows where an owned value applies.

Examples:

- organization-owned settings that only apply to one user
- carrier-owned settings that only apply inside one business entity
- shipping-method settings that only apply to one segment

Use this rule:

- if a model truly owns the setting, make it the owner
- if a model only narrows where that owner-scoped value applies, make it the
  boundary

### Resolution chain

A resolution chain is an ordered list of exact targets from highest precedence
to lowest precedence.

The package never assumes one chain automatically implies another. The caller
must declare it.

## Basic Usage

Import the facade:

```php
use Cline\Settings\Facades\Settings;
```

### Resolve app settings

```php
$settings = Settings::for($user)
    ->fallbackToApp()
    ->get(ShipmentPricingSettings::class);
```

### Save app settings

```php
Settings::for($user)->save(
    new ShipmentPricingSettings(
        callBeforeDeliveryFee: 2.5,
        returnFreightDocFee: 0.5,
    ),
);
```

If no explicit target is configured, the conductor defaults to app scope.

### Save owner-scoped settings

```php
Settings::for($user)
    ->ownedBy($carrier)
    ->save(new ShipmentPricingSettings(
        callBeforeDeliveryFee: 4.5,
        returnFreightDocFee: 0.75,
    ));
```

### Resolve owner-scoped settings with fallback

```php
$settings = Settings::for($user)
    ->ownedBy($carrier)
    ->fallbackToApp()
    ->get(ShipmentPricingSettings::class);
```

## Multi-Level Ownership

### Shipping method -> carrier -> business entity -> app

This is the most important example for a system with shipping and
business-entity scoping.

```php
$resolved = Settings::for($user)
    ->ownedBy($shippingMethod)
    ->fallbackTo($carrier)
    ->fallbackTo($businessEntity)
    ->fallbackToApp()
    ->get(ShipmentPricingSettings::class);
```

Interpretation:

- `ShippingMethod` stores the most specific override
- `Carrier` stores carrier-wide settings
- `BusinessEntity` stores country or legal-entity defaults
- app scope stores the global fallback

This is the package-native version of problems that otherwise show up as:

- `WALLET_AUTO_DEPOSIT_MIN_AMOUNT`
- `WALLET_AUTO_DEPOSIT_MIN_AMOUNT_3`
- per-entity env var suffix conventions
- custom fallback branches scattered across services

### Organization + user boundary

```php
$resolved = Settings::for($user)
    ->ownedBy($organization, $user)
    ->fallbackTo($organization)
    ->fallbackToApp()
    ->get(OrganizationMemberSettings::class);
```

Interpretation:

- the organization owns the settings namespace
- the user boundary means the row only applies to that user in that
  organization
- if no user-specific override exists, organization-wide settings apply

### Membership-like modeling

If your domain says the membership record itself owns the setting, then use the
membership as the owner:

```php
Settings::for($user)
    ->ownedBy($membership)
    ->fallbackTo($organization)
    ->fallbackToApp()
    ->get(SomeMembershipSettings::class);
```

If your domain says the organization owns the setting but it applies only to
one user, use:

```php
Settings::for($user)
    ->ownedBy($organization, $user)
    ->fallbackTo($organization)
    ->fallbackToApp()
    ->get(SomeMembershipSettings::class);
```

The package deliberately does not force one interpretation.

## Provenance

Use `getResolved()` when you need both the resolved settings object and the
source of each property.

```php
$resolved = Settings::for($user)
    ->ownedBy($carrier)
    ->fallbackTo($businessEntity)
    ->fallbackToApp()
    ->getResolved(ShipmentPricingSettings::class);

$resolved->settings->callBeforeDeliveryFee;
$resolved->sourceFor('callBeforeDeliveryFee');
$resolved->usesDefault('returnFreightDocFee');
```

This is useful for:

- admin UIs
- support tooling
- debugging unexpected inheritance
- showing whether a value is defaulted, inherited, or overridden

## Per-Property API

The package also supports a lower-level property API.

### Set one property

```php
Settings::for($user)
    ->ownedBy($businessEntity)
    ->set(ShipmentPricingSettings::class, 'callBeforeDeliveryFee', 3.25);
```

### Resolve one property

```php
$fee = Settings::for($user)
    ->ownedBy($shippingMethod)
    ->fallbackTo($carrier)
    ->fallbackTo($businessEntity)
    ->fallbackToApp()
    ->value(ShipmentPricingSettings::class, 'callBeforeDeliveryFee');
```

Use this API for:

- adapters
- admin forms that edit one field at a time
- migration layers
- targeted operational tooling

## Atomic Saves

Saving a fully typed settings object is transactional.

```php
Settings::for($user)
    ->ownedBy($carrier)
    ->save(new CarrierCredentialSettings(
        apiToken: 'secret',
        rotatesAt: new DateTimeImmutable('+1 day'),
        enabled: true,
    ));
```

If one property fails during persistence or serialization, the full object save
is rolled back.

That matters when a settings object represents one coherent unit, such as:

- credentials plus endpoint configuration
- flags plus dependent limits
- related fee values

## Casts And Encryption

### Casts

`cline/struct` already casts common date types from the declared property
type. `DateTimeImmutable`, `DateTimeInterface`, `CarbonImmutable`,
`Carbon`, and `CarbonInterface` do not need an explicit `#[CastWith]`
attribute.

```php
<?php

namespace App\Settings;

use Cline\Struct\Attributes\Encrypted;
use Cline\Settings\Settings;
use DateTimeImmutable;
use Override;

final class CarrierCredentialSettings extends Settings
{
    public function __construct(
        #[Encrypted]
        public string $apiToken,
        public DateTimeImmutable $rotatesAt,
        public bool $enabled,
    ) {}

    public static function defaults(): array
    {
        return [
            'apiToken' => '',
            'rotatesAt' => new DateTimeImmutable('2026-01-01T00:00:00+00:00'),
            'enabled' => true,
        ];
    }

    #[Override()]
    public static function namespace(): string
    {
        return 'carrier.credentials';
    }
}
```

Only write a custom cast when the property needs non-standard
serialization. In that case, implement `Cline\Struct\Contracts\CastInterface`
and attach it with `#[CastWith(...)]`.

```php
<?php

namespace App\Settings;

use Cline\Struct\Attributes\CastWith;
use Cline\Settings\Settings;
use Override;

final class CarrierCredentialSettings extends Settings
{
    public function __construct(
        #[CastWith(CustomMoneyCast::class)]
        public Money $walletMinimum,
    ) {}

    public static function defaults(): array
    {
        return [
            'walletMinimum' => Money::EUR(5000),
        ];
    }
}
```

### Encryption

Mark any property with `#[Encrypted]`.

```php
#[Encrypted]
public string $apiToken
```

Behavior:

- cast encoding runs first
- the encoded value is JSON serialized
- the JSON payload is encrypted with Laravel's encrypter
- decryption happens before cast decoding on reads

Use encryption for:

- API keys
- bearer tokens
- passwords
- client secrets

## Forgetting Overrides

### Forget one property override

```php
Settings::for($user)
    ->ownedBy($carrier)
    ->forget(ShipmentPricingSettings::class, 'callBeforeDeliveryFee');
```

After deletion, resolution falls through to the next explicit target or to the
class default.

### Forget all overrides for one target

```php
Settings::for($user)
    ->ownedBy($organization, $user)
    ->forget(OrganizationMemberSettings::class);
```

This removes every stored property row for that settings class at that exact
target only.

## Optimistic Concurrency

Use `compareAndSet()` for property-scoped optimistic locking.

```php
$stored = Settings::for($user)
    ->ownedBy($carrier)
    ->compareAndSet(
        CarrierCredentialSettings::class,
        'enabled',
        false,
        expectedVersion: 4,
    );

$stored->version;
```

Use this when multiple writers may update the same scoped property and stale
writes should fail instead of winning silently.

## Inspection

Use the raw inspection API when you want stored rows rather than a hydrated
settings object.

```php
use Cline\Settings\Support\SettingsQuery;

$rows = Settings::inspect(new SettingsQuery(
    settingsClass: ShipmentPricingSettings::class,
));
```

Each row includes:

- settings class
- namespace
- property
- decoded value
- target
- version
- encryption metadata
- cast metadata
- updated timestamp

## Snapshots

Snapshots are the package-level import/export format.

### Export

```php
use Cline\Settings\Support\SettingsQuery;

$snapshot = Settings::export(new SettingsQuery(
    settingsClass: ShipmentPricingSettings::class,
));

$json = $snapshot->toJson();
```

### Import

```php
use Cline\Settings\Support\SettingsSnapshot;

$snapshot = SettingsSnapshot::fromArray($payload);

Settings::import($snapshot, subject: $adminUser);
```

Use snapshots for:

- backups
- fixtures
- migrations
- promoting stored settings between environments

Snapshots move stored rows. They do not materialize a resolved inheritance
graph.

## Schema Evolution

Once data exists, refactoring settings class names or property names becomes a
data-migration problem. The package includes first-class rename support for
that.

### Rename a settings class

```php
use Cline\Settings\Support\SettingsRename;

Settings::rename(SettingsRename::settingsClass(
    LegacyCarrierSettings::class,
    CarrierCredentialSettings::class,
));
```

### Rename a property

```php
Settings::rename(SettingsRename::property(
    ShipmentPricingSettings::class,
    'deliveryFee',
    'callBeforeDeliveryFee',
));
```

### Inspect conflicts first

```php
$conflicts = Settings::inspectRenameConflicts(
    SettingsRename::settingsClass(
        LegacyCarrierSettings::class,
        CarrierCredentialSettings::class,
    ),
);
```

Rename behavior:

- matching value rows move to the new coordinates
- matching audit rows are rewritten
- new `renamed` audit rows are created
- destination conflicts abort the rename instead of merging implicitly

## Audit, Replay, And Rollback

All meaningful write-side operations are audited.

Examples of audited actions:

- `saved`
- `deleted`
- `purged`
- `imported`
- `renamed`

### Query audit history

```php
use Cline\Settings\Support\SettingsAuditQuery;

$entries = Settings::audit(new SettingsAuditQuery(
    settingsClass: ShipmentPricingSettings::class,
));
```

Each audit entry contains:

- audit id
- action
- settings class and namespace
- property
- exact target
- subject
- old value
- new value
- timestamp

### Replay an audit row

Replay reapplies the final state represented by one audit entry.

```php
Settings::replay($auditId, subject: $adminUser);
```

### Roll back an audit row

Rollback restores the state that existed before that audit row was recorded.

```php
Settings::rollback($auditId, subject: $adminUser);
```

These are operational tools, not normal application flow.

## Artisan Commands

### `settings:list`

List stored rows as JSON.

```bash
php artisan settings:list
php artisan settings:list --settings="App\\Settings\\ShipmentPricingSettings"
php artisan settings:list --target="carrier:12"
```

Options:

- `--settings=`
- `--property=`
- `--target=`

### `settings:resolve`

Resolve a typed settings class through an explicit chain.

```bash
php artisan settings:resolve "App\\Settings\\ShipmentPricingSettings" \
  --target="shipping_method:44" \
  --fallback="carrier:12" \
  --fallback="business_entity:1" \
  --fallback="app"
```

### `settings:diff`

Diff two exact targets for one settings class.

```bash
php artisan settings:diff "App\\Settings\\ShipmentPricingSettings" \
  --left="carrier:12" \
  --right="business_entity:1"
```

### `settings:export`

Export stored rows to a snapshot file.

```bash
php artisan settings:export storage/app/settings.json
php artisan settings:export storage/app/carrier-settings.json \
  --settings="App\\Settings\\CarrierCredentialSettings" \
  --target="carrier:12"
```

### `settings:import`

Import a snapshot file.

```bash
php artisan settings:import storage/app/settings.json
```

### `settings:prune`

Prune stale rows by `updated_at`.

```bash
php artisan settings:prune
php artisan settings:prune --settings="App\\Settings\\ShipmentPricingSettings" --days=90
```

### `settings:audit`

List audit rows as JSON.

```bash
php artisan settings:audit
php artisan settings:audit --settings="App\\Settings\\ShipmentPricingSettings"
php artisan settings:audit --target="carrier:12"
```

### `settings:conflicts`

Preview schema rename conflicts.

```bash
php artisan settings:conflicts \
  "App\\Settings\\LegacyCarrierSettings" \
  "App\\Settings\\CarrierCredentialSettings"
```

Property-level rename preview:

```bash
php artisan settings:conflicts \
  "App\\Settings\\ShipmentPricingSettings" \
  "App\\Settings\\ShipmentPricingSettings" \
  --from-property="deliveryFee" \
  --to-property="callBeforeDeliveryFee"
```

### `settings:replay`

```bash
php artisan settings:replay 123
```

### `settings:rollback`

```bash
php artisan settings:rollback 123
```

## Events

The package dispatches lifecycle events around high-level resolution and save
operations.

Available events:

- `Cline\Settings\Events\ResolvingSettings`
- `Cline\Settings\Events\SettingsResolved`
- `Cline\Settings\Events\SavingSettings`
- `Cline\Settings\Events\SettingsSaved`

Example listener:

```php
use Cline\Settings\Events\SettingsSaved;
use Illuminate\Support\Facades\Event;

Event::listen(SettingsSaved::class, function (SettingsSaved $event): void {
    logger()->info('Settings saved', [
        'settings_class' => $event->settingsClass,
        'values' => $event->values,
    ]);
});
```

## Recommended Modeling

For systems with business entities, organizations, users, carriers, and
shipping methods, a strong default strategy is:

- app scope for global defaults
- business entity scope for regional or legal-entity defaults
- carrier scope for carrier-wide credentials and options
- shipping method scope for method-specific overrides
- organization scope for tenant-level behavior
- organization + user boundary for tenant-owned per-user exceptions

Examples:

- `CarrierCredentialSettings` owned by `Carrier`
- `ShipmentPricingSettings` owned by `ShippingMethod`, with fallback to
  `Carrier`, then `BusinessEntity`, then app
- tenant member limits owned by `Organization`, bounded by `User`

## Testing Guidance

The simplest way to test package integrations is to model the real resolution
chain directly.

Useful feature-test patterns:

- save at `BusinessEntity`, resolve from `ShippingMethod -> Carrier ->
  BusinessEntity -> App`
- save at `Organization`, override at `Organization + User`
- assert provenance through `getResolved()`
- assert `forget()` re-exposes lower-precedence values
- assert `compareAndSet()` rejects stale versions
- assert rename conflict detection before applying a schema evolution

## Limitations

This package intentionally does not:

- infer fallback order automatically
- decide whether your membership should be an owner or a boundary
- ship an env-var migration layer
- ship a non-database backend

Those decisions stay explicit so the package remains predictable.
