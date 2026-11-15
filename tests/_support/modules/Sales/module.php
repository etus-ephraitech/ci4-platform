<?php

return [
    'requires'    => ['Crm'],
    'permissions' => ['sales.view' => 'See sales'],
    'matrix'      => ['admin' => ['sales.view']],
    'widgets'     => [['cell' => 'Modules\Sales\Cells\Pipeline::show', 'dashboards' => ['home']]],
];
