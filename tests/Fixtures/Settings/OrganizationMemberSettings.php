<?php declare(strict_types=1);

/**
 * Copyright (C) Brian Faust
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Tests\Fixtures\Settings;

use Cline\Settings\AbstractSettings;
use Override;

/**
 * @author Brian Faust <brian@cline.sh>
 * @internal
 * @psalm-immutable
 */
final readonly class OrganizationMemberSettings extends AbstractSettings
{
    public function __construct(
        public bool $canUsePrioritySupport,
        public int $dailyShipmentLimit,
    ) {}

    #[Override()]
    public static function defaults(): array
    {
        return [
            'canUsePrioritySupport' => false,
            'dailyShipmentLimit' => 10,
        ];
    }

    #[Override()]
    public static function namespace(): string
    {
        return 'organization.member';
    }
}
