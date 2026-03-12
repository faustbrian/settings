<?php declare(strict_types=1);

/**
 * Copyright (C) Brian Faust
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

use Cline\Settings\Database\Models\SettingAudit;
use Cline\Settings\Database\Models\StoredSetting;

return [
    /*
    |--------------------------------------------------------------------------
    | Default Primary Key Type
    |--------------------------------------------------------------------------
    |
    | This option controls the type of primary key used by the package's own
    | database models. The default "id" type uses auto-incrementing integers.
    | You may also choose "ulid" or "uuid" to align settings storage with the
    | identifier strategy used across the rest of your application.
    |
    | Supported: "id", "ulid", "uuid"
    |
    */

    'primary_key_type' => env('SETTINGS_PRIMARY_KEY_TYPE', 'id'),
    /*
    |--------------------------------------------------------------------------
    | Polymorphic Relationship Types
    |--------------------------------------------------------------------------
    |
    | These options control how owner, boundary, and subject identifiers are
    | stored in the database. Use "string" when your application may reference
    | mixed model identifier strategies, or choose "numeric", "uuid", or
    | "ulid" when all participating models use a consistent morph key type.
    |
    | Owners identify who the setting belongs to. Boundaries narrow where an
    | owner-scoped value applies. Subjects identify the actor recorded in audit
    | rows for write and replay operations.
    |
    */

    'owner_morph_type' => env('SETTINGS_OWNER_MORPH_TYPE', 'string'),
    'boundary_morph_type' => env('SETTINGS_BOUNDARY_MORPH_TYPE', 'string'),
    'subject_morph_type' => env('SETTINGS_SUBJECT_MORPH_TYPE', 'string'),
    /*
    |--------------------------------------------------------------------------
    | Eloquent Models
    |--------------------------------------------------------------------------
    |
    | These models back stored settings rows and audit history. You may replace
    | them with subclasses if your application needs custom behavior while
    | preserving the package contract.
    |
    */

    'models' => [
        'setting_audit' => SettingAudit::class,
        'stored_setting' => StoredSetting::class,
    ],
    /*
    |--------------------------------------------------------------------------
    | Table Names
    |--------------------------------------------------------------------------
    |
    | These options control the table names used by the package's value and
    | audit storage models.
    |
    */

    'table_names' => [
        'audits' => 'settings_audits',
        'migrations' => 'settings_migrations',
        'values' => 'settings_values',
    ],
    /*
    |--------------------------------------------------------------------------
    | Settings Migration Paths
    |--------------------------------------------------------------------------
    |
    | These paths are scanned by the package's settings migration runner. Each
    | file should return a class extending `Cline\Settings\Migrations\
    | SettingsMigration`, similar to a standard Laravel migration but focused
    | on evolving stored settings values instead of database schema.
    |
    */

    'migrations' => [
        'paths' => [
            database_path('settings'),
        ],
    ],
    /*
    |--------------------------------------------------------------------------
    | Polymorphic Key Mapping
    |--------------------------------------------------------------------------
    |
    | This option allows you to specify which column should be used as the
    | foreign key for each model in owner, boundary, and subject polymorphic
    | references. This is useful when different models use different key
    | columns, such as a mix of "id", "uuid", and "ulid".
    |
    | Note: Configure either 'morphKeyMap' or 'enforceMorphKeyMap', not both.
    |
    */

    'morphKeyMap' => [
        // App\Models\User::class => 'id',
        // App\Models\Organization::class => 'uuid',
    ],
    /*
    |--------------------------------------------------------------------------
    | Enforced Polymorphic Key Mapping
    |--------------------------------------------------------------------------
    |
    | This option behaves like 'morphKeyMap' but enables strict enforcement.
    | Any polymorphic model reference without an explicit mapping will throw an
    | exception instead of falling back to the model's default key column.
    |
    */

    'enforceMorphKeyMap' => [
        // App\Models\User::class => 'id',
        // App\Models\Organization::class => 'uuid',
    ],
];
