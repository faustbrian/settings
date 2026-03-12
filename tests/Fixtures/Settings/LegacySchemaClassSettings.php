<?php declare(strict_types=1);

/**
 * Copyright (C) Brian Faust
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Tests\Fixtures\Settings;

use Cline\Settings\Settings;
use Override;

/**
 * @author Brian Faust <brian@cline.sh>
 * @internal
 * @psalm-immutable
 */
final readonly class LegacySchemaClassSettings extends Settings
{
    public function __construct(
        public string $apiKey,
        public bool $enabled,
    ) {}

    #[Override()]
    public static function defaults(): array
    {
        return [
            'apiKey' => '',
            'enabled' => true,
        ];
    }

    #[Override()]
    public static function namespace(): string
    {
        return 'legacy.schema.class';
    }
}
