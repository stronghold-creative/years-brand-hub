<?php
declare(strict_types=1);

/**
 * Global styling, set once in the admin and inherited by every block.
 *
 * Each field becomes a CSS custom property or a data attribute on <body>.
 * Blocks then either inherit (the default) or override locally, which is what
 * makes a change here ripple through the whole site.
 */
function style_fields(): array
{
    return [
        'Shape and space' => [
            'radius' => [
                'label'   => 'Corner radius',
                'default' => 'soft',
                'options' => ['sharp' => 'Sharp', 'soft' => 'Soft', 'round' => 'Rounded', 'pill' => 'Very rounded'],
                'css'     => ['--s-radius' => ['sharp' => '0', 'soft' => '6px', 'round' => '14px', 'pill' => '24px']],
            ],
            'density' => [
                'label'   => 'Space between blocks',
                'default' => 'normal',
                'options' => ['compact' => 'Compact', 'normal' => 'Normal', 'roomy' => 'Roomy'],
                'css'     => ['--s-gap' => ['compact' => '2.5rem', 'normal' => '4rem', 'roomy' => '6rem']],
            ],
            'pad' => [
                'label'   => 'Padding inside panels',
                'default' => 'normal',
                'options' => ['tight' => 'Tight', 'normal' => 'Normal', 'roomy' => 'Roomy'],
                'css'     => ['--s-pad' => ['tight' => '1rem', 'normal' => '1.75rem', 'roomy' => '2.75rem']],
            ],
        ],

        'Type' => [
            'text_scale' => [
                'label'   => 'Body text size',
                'default' => 'normal',
                'options' => ['small' => 'Small', 'normal' => 'Normal', 'large' => 'Large'],
                'css'     => ['--s-text' => ['small' => '.9375rem', 'normal' => '1rem', 'large' => '1.125rem']],
            ],
            'heading_weight' => [
                'label'   => 'Heading weight',
                'default' => 'regular',
                'options' => ['regular' => 'Regular', 'medium' => 'Medium', 'bold' => 'Bold'],
                'css'     => ['--s-headweight' => ['regular' => '400', 'medium' => '500', 'bold' => '700']],
            ],
            'measure' => [
                'label'   => 'Reading width',
                'default' => 'normal',
                'options' => ['narrow' => 'Narrow', 'normal' => 'Normal', 'wide' => 'Wide'],
                'css'     => ['--s-measure' => ['narrow' => '56ch', 'normal' => '66ch', 'wide' => '78ch']],
            ],
        ],

        'Calls to action' => [
            'cta_style' => [
                'label'   => 'Button style',
                'default' => 'filled',
                'options' => ['filled' => 'Filled', 'outlined' => 'Outlined', 'text' => 'Text only'],
                'body'    => 'cta',
            ],
            'cta_shape' => [
                'label'   => 'Button shape',
                'default' => 'inherit',
                'options' => ['inherit' => 'Match corner radius', 'square' => 'Square', 'pill' => 'Pill'],
                'css'     => ['--s-cta-radius' => ['inherit' => 'var(--s-radius)', 'square' => '0', 'pill' => '999px']],
            ],
        ],

        'Panels and cards' => [
            'card_style' => [
                'label'   => 'Panel style',
                'default' => 'flat',
                'options' => ['flat' => 'Flat fill', 'outlined' => 'Outlined', 'shadow' => 'Soft shadow'],
                'body'    => 'card',
            ],
        ],

        'Colour swatches' => [
            'swatch_layout' => [
                'label'   => 'Default layout',
                'default' => 'list',
                'options' => ['list' => 'List', 'cards' => 'Cards', 'grid' => 'Grid'],
                'body'    => 'swatch',
            ],
            'swatch_shape' => [
                'label'   => 'Swatch shape',
                'default' => 'square',
                'options' => ['square' => 'Square', 'rounded' => 'Rounded', 'circle' => 'Circle'],
                'body'    => 'swatchshape',
            ],
        ],
    ];
}

/** Flattens the grouped definition to key => field. */
function style_flat(): array
{
    $out = [];
    foreach (style_fields() as $fields) {
        foreach ($fields as $key => $field) {
            $out[$key] = $field;
        }
    }
    return $out;
}

function style_value(string $key): string
{
    $field = style_flat()[$key] ?? null;
    if (!$field) {
        return '';
    }
    $stored = (string) setting('style.' . $key, '');
    return isset($field['options'][$stored]) ? $stored : (string) $field['default'];
}

function style_save(string $key, string $value): void
{
    $field = style_flat()[$key] ?? null;
    if (!$field) {
        return;
    }
    setting_save('style.' . $key, isset($field['options'][$value]) ? $value : (string) $field['default']);
}

/** The custom properties for the current settings, for the document head. */
function style_css(): string
{
    $lines = [];
    foreach (style_flat() as $key => $field) {
        if (empty($field['css'])) {
            continue;
        }
        $value = style_value($key);
        foreach ($field['css'] as $property => $map) {
            if (isset($map[$value])) {
                $lines[] = '  ' . $property . ': ' . $map[$value] . ';';
            }
        }
    }
    return $lines ? ":root {\n" . implode("\n", $lines) . "\n}" : '';
}

/** Data attributes on <body>, for settings that switch a whole treatment. */
function style_body_attributes(): string
{
    $out = '';
    foreach (style_flat() as $key => $field) {
        if (empty($field['body'])) {
            continue;
        }
        $out .= ' data-' . $field['body'] . '="' . e(style_value($key)) . '"';
    }
    return $out;
}
