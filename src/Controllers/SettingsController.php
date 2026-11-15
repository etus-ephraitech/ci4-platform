<?php

declare(strict_types=1);

namespace Ephraitech\Platform\Controllers;

use CodeIgniter\Exceptions\PageNotFoundException;
use CodeIgniter\HTTP\RedirectResponse;
use Ephraitech\Platform\Forms\SettingsForm;

/**
 * Settings groups declared by switched-on modules. In a multi-tenant
 * install, workspace settings are changed inside a workspace and global
 * settings by the platform owner with no workspace selected.
 */
class SettingsController extends BaseModuleController
{
    public function index(?string $group = null): string|RedirectResponse
    {
        $groups = $this->visibleGroups();

        if ($groups === []) {
            return redirect()->route('core.dashboard')->with('error', 'There are no settings you can change here.');
        }

        $group ??= (string) array_key_first($groups);
        $this->ensureGroup($groups, $group);

        $form = $this->form($groups, $group)->fill(service('platformSettings')->group($group));

        return $this->renderPage($groups, $group, $form);
    }

    public function update(string $group): string|RedirectResponse
    {
        $groups = $this->visibleGroups();
        $this->ensureGroup($groups, $group);

        $form = $this->form($groups, $group);

        if (! $form->validate()) {
            return $this->renderPage($groups, $group, $form);
        }

        $changes = service('platformSettings')->save($group, $form->validated(), user_id());

        if ($changes === []) {
            return redirect()->route('core.settings.group', [$group])->with('message', 'No changes to save.');
        }

        audit($groups[$group]['module'], 'settings.updated', 'Changed ' . $groups[$group]['title'] . ' settings', 'settings', $group, $changes);

        return redirect()->route('core.settings.group', [$group])->with('message', $groups[$group]['title'] . ' saved.');
    }

    // ------------------------------------------------------------------

    /**
     * @return array<string, array<string, mixed>>
     */
    private function visibleGroups(): array
    {
        return array_filter(
            service('platformSettings')->editableGroups(),
            static fn (array $group): bool => allowed($group['permission'])
        );
    }

    /**
     * @param array<string, array<string, mixed>> $groups
     */
    private function ensureGroup(array $groups, string $group): void
    {
        if (! isset($groups[$group])) {
            throw PageNotFoundException::forPageNotFound('No settings group named "' . esc($group) . '".');
        }
    }

    /**
     * @param array<string, array<string, mixed>> $groups
     */
    private function form(array $groups, string $group): SettingsForm
    {
        return (new SettingsForm($group, $groups[$group]['fields']))->action(url_to('core.settings.update', $group));
    }

    /**
     * @param array<string, array<string, mixed>> $groups
     */
    private function renderPage(array $groups, string $group, SettingsForm $form): string
    {
        return $this->page('Ephraitech\Platform\Views\settings\index', 'Settings', [
            'groups'  => $groups,
            'current' => $group,
            'form'    => $form,
        ], [['Settings', url_to('core.settings')], [$groups[$group]['title']]]);
    }
}
