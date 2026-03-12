<?php declare(strict_types=1);

/**
 * Copyright (C) Brian Faust
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Cline\Settings\Support;

use DateTimeImmutable;

/**
 * Immutable persistence record returned from the storage layer.
 *
 * This type captures the raw database coordinate for one stored property
 * before codec decoding is applied. Repositories return records at the
 * boundary between persistence and domain resolution so higher layers can
 * preserve versioning, timestamps, and the exact owner/boundary target that
 * produced the match.
 *
 * The payload remains in repository format on purpose. Consumers that need the
 * hydrated runtime value should pass the record through
 * {@see SettingsValueCodec::toStoredValue()} rather than reading
 * {@see $payload} directly.
 *
 * @author Brian Faust <brian@cline.sh>
 * @psalm-immutable
 */
final readonly class StoredSettingRecord
{
    /**
     * @param array<string, mixed> $payload
     *
     * The payload is the encoded repository representation for the property,
     * including `data` plus serialization metadata such as cast and
     * encryption flags.
     */
    public function __construct(
        public string $settingsClass,
        public string $namespace,
        public string $property,
        public array $payload,
        public ResolutionTarget $target,
        public int $version,
        public ?DateTimeImmutable $updatedAt = null,
    ) {}
}
