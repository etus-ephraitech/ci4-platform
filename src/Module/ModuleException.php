<?php

declare(strict_types=1);

namespace Ephraitech\Platform\Module;

use RuntimeException;

/**
 * A module is missing, misconfigured or clashes with another.
 */
final class ModuleException extends RuntimeException
{
}
