<?php

declare(strict_types=1);

namespace Modules\Crm\Cells;

class Banner
{
    public function app(): string
    {
        return '<div class="crm-banner">Clients banner</div>';
    }

    public function auth(): string
    {
        return '<div class="crm-auth-banner">Sign-in banner</div>';
    }
}
