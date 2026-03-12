<?php declare(strict_types=1);

/**
 * Copyright (C) Brian Faust
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Cline\Settings\Commands;

use Cline\Settings\Exceptions\InvalidCommandTargetReferenceException;
use Cline\Settings\Support\Reference;
use Cline\Settings\Support\ResolutionTarget;

use function array_pad;
use function explode;
use function mb_trim;

/**
 * Parses CLI target filters into structured resolution targets.
 *
 * Console commands expose a `type:id|type:id` syntax so operators can scope
 * inspection and export operations without constructing
 * {@see ResolutionTarget} objects manually. This parser is the narrow adapter
 * between that textual interface and the package's canonical target model.
 *
 * Accepted forms are:
 * - `app` or an empty string for the application-level target
 * - `owner-type:owner-id` for an owner-scoped target
 * - `owner-type:owner-id|boundary-type:boundary-id` for an owner constrained
 *   by a boundary
 *
 * Invalid input is rejected eagerly. The parser does not attempt to normalize
 * malformed fragments, because command failures are preferable to inspecting
 * or exporting the wrong persistence scope.
 *
 * @author Brian Faust <brian@cline.sh>
 *
 * @internal
 */
final class CommandTargetParser
{
    /**
     * Parse one CLI target expression into the package resolution model.
     *
     * Empty input and the explicit `app` alias both resolve to the application
     * target, which represents the root of the precedence chain. Any other
     * value must provide an owner reference and may optionally append a
     * boundary reference separated by `|`.
     *
     * @throws InvalidCommandTargetReferenceException When any reference
     *                                                fragment omits the
     *                                                required `type:id`
     *                                                structure
     */
    public static function parse(string $value): ResolutionTarget
    {
        $trimmed = mb_trim($value);

        if ($trimmed === '' || $trimmed === 'app') {
            return ResolutionTarget::app();
        }

        [$owner, $boundary] = array_pad(explode('|', $trimmed, 2), 2, null);

        return new ResolutionTarget(self::parseReference($owner), $boundary !== null ? self::parseReference($boundary) : null);
    }

    /**
     * Parse one reference fragment from the CLI syntax.
     *
     * Each fragment maps directly to the persisted morph-type and identifier
     * pair used by stored settings rows. The method is intentionally strict so
     * command filters cannot silently broaden or redirect a query.
     *
     * @throws InvalidCommandTargetReferenceException When the fragment is
     *                                                missing or does not use
     *                                                the `type:id` format
     */
    private static function parseReference(?string $value): Reference
    {
        if ($value === null) {
            throw InvalidCommandTargetReferenceException::forValue($value);
        }

        [$type, $id] = array_pad(explode(':', $value, 2), 2, null);

        if ($type === null || $type === '' || $id === null || $id === '') {
            throw InvalidCommandTargetReferenceException::forValue($value);
        }

        return new Reference($type, $id);
    }
}
