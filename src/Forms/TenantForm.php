<?php

declare(strict_types=1);

namespace Ephraitech\Platform\Forms;

use Ephraitech\Forms\Form;
use Ephraitech\Platform\Models\TenantModel;

/**
 * Create a workspace (isNew) or change one's name, plan, status and domain.
 */
class TenantForm extends Form
{
    /**
     * @var array<string, string> plan key => title
     */
    private array $planOptions;

    private bool $isNew;

    private string $baseDomain;

    /**
     * @param array<string, string> $planOptions
     */
    public function __construct(array $planOptions, bool $isNew, string $baseDomain)
    {
        $this->planOptions = $planOptions;
        $this->isNew       = $isNew;
        $this->baseDomain  = $baseDomain;

        parent::__construct('tenant');
    }

    protected function define(): void
    {
        $this->fieldset('Workspace', function (): void {
            $this->text('name', 'Name')->rules('required|max_length[150]')->autofocus()
                ->placeholder('e.g. Acme Supplies Ltd');

            if ($this->isNew) {
                $this->text('slug', 'Address')
                    ->rules('required|min_length[1]|max_length[63]|regex_match[/^[a-z0-9](?:[a-z0-9-]{0,61}[a-z0-9])?$/]')
                    ->placeholder('acme')
                    ->help($this->baseDomain !== ''
                        ? 'Lowercase letters, digits and hyphens. The workspace opens at <address>.' . $this->baseDomain . '. It cannot be changed later.'
                        : 'Lowercase letters, digits and hyphens. Used to identify the workspace; it cannot be changed later.');
            }

            $this->row(function (): void {
                $this->select('plan', 'Plan', $this->planOptions)->rules('required|in_list[' . implode(',', array_keys($this->planOptions)) . ']')->width(6);
                $this->select('status', 'Status', TenantModel::STATUSES)->rules('required|in_list[' . implode(',', array_keys(TenantModel::STATUSES)) . ']')->default('active')->width(6);
            });

            $this->row(function (): void {
                $this->date('trial_ends_at', 'Trial ends')->rules('permit_empty|valid_date[Y-m-d]')->help('For the Trial status: the workspace closes after this day.')->width(6);
                $this->text('domain', 'Own domain')->rules('permit_empty|max_length[190]|regex_match[/^([a-z0-9-]+\.)+[a-z]{2,}$/]')->placeholder('erp.acme.co.ug')->help('Optional. Point its DNS at this server.')->width(6);
            });
        });

        if ($this->isNew) {
            $this->fieldset('Owner', function (): void {
                $this->email('owner_email', 'Owner email')
                    ->rules('permit_empty|valid_email|max_length[150]')
                    ->help('An existing account becomes the owner; a new email gets an account and a link to choose a password. Leave empty to add people later.');
                $this->text('owner_name', 'Owner name')->rules('permit_empty|max_length[100]')->help('Used only when a new account is created.');
            });
        }

        $this->submit($this->isNew ? 'Create workspace' : 'Save changes');
    }
}
