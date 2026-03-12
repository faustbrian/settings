<?php declare(strict_types=1);

/**
 * Copyright (C) Brian Faust
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Cline\Settings\Support;

use Cline\Settings\Contracts\SettingsValueCodecInterface;
use Cline\Settings\Exceptions\SettingsSerializationException;
use Cline\Struct\Contracts\CastInterface;
use Illuminate\Contracts\Encryption\Encrypter;
use Throwable;

use const JSON_THROW_ON_ERROR;

use function is_string;
use function json_decode;
use function json_encode;

/**
 * Encodes and decodes settings property payloads for persistence.
 *
 * The codec is the serialization boundary between typed runtime values and the
 * repository payload schema. It is responsible for applying property-level
 * casts, optional encryption, and consistent exception translation when
 * serialization fails.
 *
 * The repository deliberately stores opaque payload arrays instead of domain
 * values. That keeps persistence format concerns isolated here and allows the
 * rest of the package to work with decoded values and typed settings objects.
 *
 * @author Brian Faust <brian@cline.sh>
 * @psalm-immutable
 */
final readonly class SettingsValueCodec implements SettingsValueCodecInterface
{
    public function __construct(
        private Encrypter $encrypter,
    ) {}

    /**
     * @param class-string $settingsClass
     *
     * @return array{cast: null|string, data: mixed, encrypted: bool}
     *
     * Encode a runtime property value into the payload shape expected by the
     * repository. Cast encoding runs before encryption so encrypted payloads
     * always contain the cast's serialized form, not the original runtime
     * value. Any serialization, casting, JSON, or encryption failure is
     * wrapped as a `SettingsSerializationException` for the specific property.
     */
    public function encode(SettingsDefinition $definition, string $settingsClass, string $property, mixed $value): array
    {
        try {
            $cast = $definition->castFor($property);
            $propertyMetadata = $definition->properties()[$property];
            $encoded = $cast?->set($propertyMetadata, $value) ?? $value;
            $castClass = $cast instanceof CastInterface ? $cast::class : null;

            if ($definition->isEncrypted($property)) {
                return [
                    'cast' => $castClass,
                    'data' => $this->encrypter->encrypt(
                        json_encode($encoded, JSON_THROW_ON_ERROR),
                        false,
                    ),
                    'encrypted' => true,
                ];
            }

            json_encode($encoded, JSON_THROW_ON_ERROR);

            return [
                'cast' => $castClass,
                'data' => $encoded,
                'encrypted' => false,
            ];
        } catch (Throwable $throwable) {
            throw SettingsSerializationException::forProperty(
                $settingsClass,
                $property,
                $throwable,
            );
        }
    }

    /**
     * @param class-string         $settingsClass
     * @param array<string, mixed> $payload
     *
     * Decode a repository payload back into the runtime property value. When
     * the payload is marked as encrypted, decryption happens before cast
     * decoding. Unencrypted values are passed directly through the configured
     * cast, if any.
     */
    public function decode(SettingsDefinition $definition, string $settingsClass, string $property, array $payload): mixed
    {
        try {
            $data = $payload['data'] ?? null;
            $cast = $definition->castFor($property);
            $propertyMetadata = $definition->properties()[$property];

            if (($payload['encrypted'] ?? false) === true && is_string($data)) {
                $decrypted = $this->encrypter->decrypt($data, false);

                if (!is_string($decrypted)) {
                    return $cast?->get($propertyMetadata, $decrypted) ?? $decrypted;
                }

                $decoded = json_decode(
                    $decrypted,
                    true,
                    512,
                    JSON_THROW_ON_ERROR,
                );

                return $cast?->get($propertyMetadata, $decoded) ?? $decoded;
            }

            return $cast?->get($propertyMetadata, $data) ?? $data;
        } catch (Throwable $throwable) {
            throw SettingsSerializationException::forProperty(
                $settingsClass,
                $property,
                $throwable,
            );
        }
    }

    /**
     * @param class-string $settingsClass
     *
     * Rehydrate a raw repository record into a decoded `StoredValue` while
     * preserving version, target, and payload metadata for provenance and
     * audit use cases.
     */
    public function toStoredValue(
        SettingsDefinition $definition,
        string $settingsClass,
        StoredSettingRecord $record,
    ): StoredValue {
        return new StoredValue(
            $record->settingsClass,
            $record->namespace,
            $record->property,
            $this->decode($definition, $settingsClass, $record->property, $record->payload),
            $record->target,
            $record->version,
            (bool) ($record->payload['encrypted'] ?? false),
            is_string($record->payload['cast'] ?? null) ? $record->payload['cast'] : null,
            $record->updatedAt,
        );
    }
}
