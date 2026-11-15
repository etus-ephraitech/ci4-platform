<?php

declare(strict_types=1);

namespace Ephraitech\Platform\Users\Forms;

use Ephraitech\Forms\Form;

/**
 * Create a staff account (isNew) or edit someone's details.
 * Roles and passwords are changed on the profile page, not here, when editing.
 */
class StaffForm extends Form
{
    /**
     * @var array<string, string> role key => title
     */
    private array $roleOptions;

    private bool $isNew;

    private bool $phoneEnabled;

    /**
     * @param array<string, string> $roleOptions
     */
    public function __construct(array $roleOptions, bool $isNew, bool $phoneEnabled)
    {
        $this->roleOptions  = $roleOptions;
        $this->isNew        = $isNew;
        $this->phoneEnabled = $phoneEnabled;

        parent::__construct('staff');
    }

    protected function define(): void
    {
        $this->fieldset('Person', function (): void {
            $this->row(function (): void {
                $this->text('first_name', 'First name')->rules('required|max_length[100]')->autofocus()->width(6);
                $this->text('last_name', 'Last name')->rules('permit_empty|max_length[100]')->width(6);
            });

            $this->text('job_title', 'Job title')
                ->rules('permit_empty|max_length[120]')
                ->placeholder('e.g. Accountant, Sales officer');
        });

        $this->fieldset('Sign-in', function (): void {
            $this->row(function (): void {
                $this->email('email', 'Email')
                    ->rules('required|valid_email|max_length[150]')
                    ->placeholder('name@company.com')
                    ->help('They sign in with this.')
                    ->width($this->phoneEnabled ? 6 : 12);

                if ($this->phoneEnabled) {
                    $this->tel('phone', 'Phone')
                        ->rules('permit_empty|max_length[20]')
                        ->placeholder('0772 123 456')
                        ->help('They can also sign in with this.')
                        ->width(6);
                }
            });
        });

        if ($this->isNew) {
            $this->fieldset('Access', function (): void {
                $this->checkboxes('roles', 'Roles', $this->roleOptions)
                    ->rules('required')
                    ->default(array_key_exists('member', $this->roleOptions) ? ['member'] : [])
                    ->help('What they can see and do. You can change this any time.');

                $this->radio('password_mode', 'Password', [
                    'link'     => 'Email them a link to choose their own password',
                    'generate' => 'Create a temporary password I will give them',
                ])->rules('required|in_list[link,generate]')->default('link');
            });
        }

        $this->submit($this->isNew ? 'Create account' : 'Save details');
    }
}
