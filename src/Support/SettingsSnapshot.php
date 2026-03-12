<?php declare(strict_types=1);

/**
 * Copyright (C) Brian Faust
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Cline\Settings\Support;

use Cline\Settings\Exceptions\SettingsSnapshotEncodingException;
use JsonException;

use const JSON_PRETTY_PRINT;
use const JSON_THROW_ON_ERROR;

use function array_map;
use function is_array;
use function is_string;
use function json_encode;

/**
 * Portable collection of exported settings rows.
 *
 * Snapshots are the package-level interchange format for moving persisted
 * settings between processes, environments, or points in time. They are
 * intentionally storage-shaped: importing a snapshot can replay each entry
 * directly without reconstructing a full resolution chain.
 *
 * The class is immutable so export results can be passed between commands,
 * queue jobs, and tests without accidental mutation of the entry list.
 *
 * @author Brian Faust <brian@cline.sh>
 * @psalm-immutable
 */
final readonly class SettingsSnapshot
{
    /**
     * @param array<int, SettingsSnapshotEntry> $entries
     *
     * Entries are expected to be in the order produced by the exporting
     * storage query. The snapshot itself does not impose or reinterpret
     * precedence semantics; it simply preserves row data.
     */
    public function __construct(
        public array $entries,
    ) {}

    /**
     * @param array<string, mixed> $payload
     *
     * Build a snapshot from a decoded JSON payload.
     *
     * Non-array or malformed entry records are ignored rather than causing the
     * entire snapshot to fail. This makes import tooling more tolerant of
     * partially edited fixture files while still normalizing all valid rows.
     */
    public static function fromArray(array $payload): self
    {
        $rawEntries = $payload['entries'] ?? null;

        if (!is_array($rawEntries)) {
            return new self([]);
        }

        $entries = [];

        foreach ($rawEntries as $entry) {
            if (!is_array($entry)) {
                continue;
            }

            $normalizedEntry = [];

            foreach ($entry as $key => $value) {
                if (!is_string($key)) {
                    continue;
                }

                $normalizedEntry[$key] = $value;
            }

            $entries[] = SettingsSnapshotEntry::fromArray($normalizedEntry);
        }

        return new self($entries);
    }

    /**
     * Convert the snapshot into the canonical export payload.
     *
     * The array shape matches the JSON document written by the export command
     * and consumed by snapshot import.
     *
     * @return array{entries: array<int, array<string, mixed>>}
     */
    public function toArray(): array
    {
        return [
            'entries' => array_map(
                static fn (SettingsSnapshotEntry $entry): array => $entry->toArray(),
                $this->entries,
            ),
        ];
    }

    /**
     * Encode the snapshot as pretty-printed JSON.
     *
     * JSON encoding errors are rethrown as
     * {@see SettingsSnapshotEncodingException} so callers that operate at the
     * package boundary do not have to depend on PHP's low-level
     * {@see JsonException} directly.
     *
     * @throws SettingsSnapshotEncodingException When the snapshot cannot be
     *                                           encoded as JSON
     */
    public function toJson(): string
    {
        try {
            return json_encode($this->toArray(), JSON_PRETTY_PRINT | JSON_THROW_ON_ERROR);
        } catch (JsonException $jsonException) {
            throw SettingsSnapshotEncodingException::fromJsonException($jsonException);
        }
    }
}
