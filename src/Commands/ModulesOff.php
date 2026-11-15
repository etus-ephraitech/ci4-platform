<?php

declare(strict_types=1);

namespace Ephraitech\Platform\Commands;

/**
 * php spark modules:off <Module> [--tenant=<slug>] [--until=YYYY-MM-DD] [--note="..."]
 */
class ModulesOff extends ModuleSwitch
{
    protected $name        = 'modules:off';
    protected $description = 'Switches a module off (single install, or one workspace with --tenant).';
    protected $usage       = 'modules:off <Module> [--tenant=<slug>] [--until=YYYY-MM-DD] [--note="..."]';

    protected function state(): ?bool
    {
        return false;
    }
}
