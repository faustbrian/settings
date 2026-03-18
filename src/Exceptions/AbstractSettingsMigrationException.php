<?php declare(strict_types=1);

/**
 * Copyright (C) Brian Faust
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Cline\Settings\Exceptions;

use LogicException;

/**
 * @author Brian Faust <brian@cline.sh>
 */
abstract class AbstractSettingsMigrationException extends LogicException implements SettingsExceptionInterface {}
