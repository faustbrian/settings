<?php declare(strict_types=1);

/**
 * Copyright (C) Brian Faust
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

use Cline\Settings\Support\Reference;
use Illuminate\Database\Eloquent\Relations\Relation;
use Tests\Fixtures\Models\Carrier;
use Tests\Fixtures\Models\User;

describe('reference', function (): void {
    test('normalizes null and existing references', function (): void {
        $reference = new Reference('carrier', '123');

        expect(Reference::from(null))->toBeNull()
            ->and(Reference::from($reference))->toBe($reference);
    });

    test('normalizes models through morph keys', function (): void {
        $carrier = Carrier::query()->create(['name' => 'DHL']);

        $reference = Reference::from($carrier);

        expect($reference)->toBeInstanceOf(Reference::class)
            ->and($reference?->type)->toBe('carrier')
            ->and($reference?->id)->toBe((string) $carrier->getKey());
    });

    test('normalizes scalar values', function (): void {
        expect(Reference::from('alpha'))->toEqual(
            new Reference('scalar', 'alpha'),
        )
            ->and(Reference::from(5))->toEqual(
                new Reference('scalar', '5'),
            )
            ->and(Reference::from(4.2))->toEqual(
                new Reference('scalar', '4.2'),
            )
            ->and(Reference::from(true))->toEqual(
                new Reference('scalar', '1'),
            )
            ->and(Reference::from(false))->toEqual(
                new Reference('scalar', '0'),
            );
    });

    test('returns null for unsupported values', function (): void {
        expect(Reference::from(
            new stdClass(),
        ))->toBeNull();
    });

    test('respects enforced morph maps when resolving model references', function (): void {
        Relation::requireMorphMap(true);

        $user = new User(['name' => 'Carol']);
        $user->setRawAttributes(['id' => 123], sync: true);

        expect(Reference::from($user)?->type)->toBe('user');
    });
});
