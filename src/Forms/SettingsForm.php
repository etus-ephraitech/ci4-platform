<?php

declare(strict_types=1);

namespace Ephraitech\Platform\Forms;

use Ephraitech\Forms\Form;
use InvalidArgumentException;

/**
 * The form for one settings group, built from the field definitions a
 * module declares in module.php.
 *
 * Field types: text, email, tel, url, number, date, textarea, select.
 * A select's 'options' is an array, or "Class::method" returning one
 * (resolved when the form is built, so manifests stay cheap to load).
 */
class SettingsForm extends Form
{
    /**
     * Named so they never collide with Ephraitech\Forms\Form's own
     * properties ($fields, $name, ...).
     */
    private string $settingsGroup;

    /**
     * @var array<string, array<string, mixed>>
     */
    private array $definitions;

    /**
     * @param array<string, array<string, mixed>> $definitions field definitions from module.php
     */
    public function __construct(string $group, array $definitions)
    {
        // Assigned before parent::__construct(), which calls define().
        $this->settingsGroup = $group;
        $this->definitions   = $definitions;

        parent::__construct('settings_' . $group);
    }

    /**
     * Resolve a select's options: an array, or "Class::method".
     *
     * @return array<string|int, string>
     */
    public static function options(mixed $options): array
    {
        if (is_array($options)) {
            return $options;
        }

        if (is_string($options) && str_contains($options, '::')) {
            [$class, $method] = explode('::', $options, 2);

            return (array) $class::$method();
        }

        return [];
    }

    protected function define(): void
    {
        foreach ($this->definitions as $key => $definition) {
            $label = (string) ($definition['label'] ?? ucfirst(str_replace('_', ' ', $key)));
            $type  = (string) ($definition['type'] ?? 'text');

            $field = match ($type) {
                'text'     => $this->text($key, $label),
                'email'    => $this->email($key, $label),
                'tel'      => $this->tel($key, $label),
                'url'      => $this->url($key, $label),
                'number'   => $this->number($key, $label),
                'date'     => $this->date($key, $label),
                'textarea' => $this->textarea($key, $label)->rows(3),
                'select'   => $this->select($key, $label, self::options($definition['options'] ?? [])),
                default    => throw new InvalidArgumentException("Setting '{$this->settingsGroup}.{$key}' has unsupported type '{$type}'."),
            };

            if (! empty($definition['rules'])) {
                $field->rules($definition['rules']);
            }

            if (! empty($definition['help'])) {
                $field->help((string) $definition['help']);
            }
        }

        $this->submit('Save changes');
    }
}
