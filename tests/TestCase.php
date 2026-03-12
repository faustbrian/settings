<?php declare(strict_types=1);

/**
 * Copyright (C) Brian Faust
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Tests;

use Cline\Morphism\MorphismServiceProvider;
use Cline\Settings\SettingsServiceProvider;
use Cline\Struct\StructServiceProvider;
use Cline\VariableKeys\VariableKeysServiceProvider;
use Illuminate\Contracts\Config\Repository;
use Illuminate\Database\Eloquent\Relations\Relation;
use Orchestra\Testbench\Attributes\WithMigration;
use Orchestra\Testbench\TestCase as BaseTestCase;
use Tests\Fixtures\Models\BusinessEntity;
use Tests\Fixtures\Models\Carrier;
use Tests\Fixtures\Models\Organization;
use Tests\Fixtures\Models\ShippingMethod;
use Tests\Fixtures\Models\User;

/**
 * @author Brian Faust <brian@cline.sh>
 * @internal
 */
#[WithMigration()]
abstract class TestCase extends BaseTestCase
{
    protected function getPackageProviders($app): array
    {
        return [
            MorphismServiceProvider::class,
            StructServiceProvider::class,
            VariableKeysServiceProvider::class,
            SettingsServiceProvider::class,
        ];
    }

    protected function defineEnvironment($app): void
    {
        $app->make(Repository::class)->set('database.default', 'sqlite');
        $app->make(Repository::class)->set('app.key', 'base64:K7j+qjMx5m6owc1HU3D29M4m8f8gS7g8pAtc8JsiK2A=');
        $app->make(Repository::class)->set('database.connections.sqlite', [
            'driver' => 'sqlite',
            'database' => ':memory:',
            'prefix' => '',
        ]);

        Relation::morphMap([
            'business_entity' => BusinessEntity::class,
            'carrier' => Carrier::class,
            'organization' => Organization::class,
            'shipping_method' => ShippingMethod::class,
            'user' => User::class,
        ], merge: false);

        Relation::requireMorphMap(false);
    }

    protected function defineDatabaseMigrations(): void
    {
        $this->loadMigrationsFrom(__DIR__.'/Fixtures/database/migrations');
        $this->loadMigrationsFrom(__DIR__.'/../database/migrations');
    }
}
