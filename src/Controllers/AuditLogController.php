<?php

declare(strict_types=1);

namespace Ephraitech\Platform\Controllers;

use Ephraitech\Platform\Models\AuditLogModel;

class AuditLogController extends BaseModuleController
{
    private const PER_PAGE = 30;

    public function index(): string
    {
        $filters = [
            'module'  => $this->cleanText($this->request->getGet('module')),
            'user_id' => $this->cleanText($this->request->getGet('user_id')),
            'from'    => $this->cleanDate($this->request->getGet('from')),
            'to'      => $this->cleanDate($this->request->getGet('to')),
            'q'       => $this->cleanText($this->request->getGet('q')),
        ];

        $model = model(AuditLogModel::class, false);
        $rows  = $model->filtered($filters)->paginate(self::PER_PAGE);

        return $this->page('Ephraitech\Platform\Views\audit\index', 'Audit log', [
            'rows'    => $rows,
            'pager'   => $model->pager,
            'filters' => $filters,
            'modules' => array_keys(service('moduleRegistry')->all()),
        ], [['Audit log']]);
    }

    private function cleanText(mixed $value): ?string
    {
        if (! is_string($value)) {
            return null;
        }

        $value = trim($value);

        return $value === '' ? null : mb_substr($value, 0, 100);
    }

    private function cleanDate(mixed $value): ?string
    {
        return is_string($value) && preg_match('/^\d{4}-\d{2}-\d{2}$/', $value) === 1 ? $value : null;
    }
}
