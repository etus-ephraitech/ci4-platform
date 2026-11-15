<?php

declare(strict_types=1);

namespace Ephraitech\Platform\Commands;

/**
 * php spark modules:on <Module> [--tenant=<slug>] [--until=YYYY-MM-DD] [--note="..."]
 */
class ModulesOn extends ModuleSwitch
{
    protected $name        = 'modules:on';
    protected $description = 'Switches a module on (single install, or one workspace with --tenant).';
    protected $usage       = 'modules:on <Module> [--tenant=<slug>] [--until=YYYY-MM-DD] [--note="..."]';

    protected function state(): ?bool
    {
        return true;
    }
}
