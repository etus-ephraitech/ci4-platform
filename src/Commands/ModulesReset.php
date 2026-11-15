<?php

declare(strict_types=1);

namespace Ephraitech\Platform\Commands;

/**
 * php spark modules:reset <Module> [--tenant=<slug>] [--until=YYYY-MM-DD] [--note="..."]
 */
class ModulesReset extends ModuleSwitch
{
    protected $name        = 'modules:reset';
    protected $description = 'Removes a module switch, so its default (single) or plan (multi) decides again.';
    protected $usage       = 'modules:reset <Module> [--tenant=<slug>] [--until=YYYY-MM-DD] [--note="..."]';

    protected function state(): ?bool
    {
        return null;
    }
}
