<?php declare(strict_types=1);

/**
 * Copyright (C) Brian Faust
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Tests\Fixtures\Settings;

use Cline\Settings\AbstractSettings;
use Cline\Struct\Attributes\Encrypted;
use Override;

/**
 * @author Brian Faust <brian@cline.sh>
 * @internal
 * @psalm-immutable
 */
final readonly class AtomicSaveFailureSettings extends AbstractSettings
{
    public function __construct(
        public string $plainValue,
        #[Encrypted()]
        public string $brokenEncryptedValue,
    ) {}

    #[Override()]
    public static function namespace(): string
    {
        return 'atomic.save.failure';
    }
}
