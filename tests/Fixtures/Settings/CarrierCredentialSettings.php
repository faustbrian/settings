<?php declare(strict_types=1);

/**
 * Copyright (C) Brian Faust
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Tests\Fixtures\Settings;

use Carbon\CarbonImmutable;
use Cline\Settings\AbstractSettings;
use Cline\Struct\Attributes\Encrypted;
use DateTimeImmutable;
use Override;

/**
 * @author Brian Faust <brian@cline.sh>
 * @internal
 * @psalm-immutable
 */
final readonly class CarrierCredentialSettings extends AbstractSettings
{
    public function __construct(
        #[Encrypted()]
        public string $apiToken,
        public DateTimeImmutable $rotatesAt,
        public bool $enabled,
    ) {}

    #[Override()]
    public static function defaults(): array
    {
        return [
            'apiToken' => '',
            'rotatesAt' => new CarbonImmutable('2026-01-01T00:00:00+00:00'),
            'enabled' => true,
        ];
    }

    #[Override()]
    public static function namespace(): string
    {
        return 'carrier.credentials';
    }
}
