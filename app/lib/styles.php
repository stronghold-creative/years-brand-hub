<?php
declare(strict_types=1);

/**
 * Global styling, set in the admin and inherited by every block.
 *
 * Most values are real numbers with a unit rather than named presets, so the
 * brand team can set a corner radius of 14px rather than picking "rounded".
 * Only genuinely categorical choices, such as whether a button is filled or
 * outlined, remain as options.
 *
 * Each field emits either a CSS custom property or a data attribute on <body>.
 */
function style_fields(): array
{
    return [
        'Shape' => [
            'radius' => [
                'label' => 'Corner radius', 'type' => 'number', 'unit' => 'px',
                'default' => 6, 'min' => 0, 'max' => 64, 'step' => 1,
                'css' => '--s-radius',
                'presets' => ['Sharp' => 0, 'Soft' => 6, 'Rounded' => 14, 'Pill' => 28],
            ],
            'border_width' => [
                'label' => 'Outline thickness', 'type' => 'number', 'unit' => 'px',
                'default' => 1, 'min' => 0, 'max' => 4, 'step' => 1,
                'css' => '--s-border',
            ],
        ],

        'Space' => [
            'gap' => [
                'label' => 'Between blocks', 'type' => 'number', 'unit' => 'rem',
                'default' => 4, 'min' => 0, 'max' => 12, 'step' => 0.25,
                'css' => '--s-gap',
                'presets' => ['Compact' => 2.5, 'Normal' => 4, 'Roomy' => 6],
            ],
            'pad' => [
                'label' => 'Inside panels', 'type' => 'number', 'unit' => 'rem',
                'default' => 1.75, 'min' => 0, 'max' => 6, 'step' => 0.25,
                'css' => '--s-pad',
            ],
            'gutter' => [
                'label' => 'Page margin', 'type' => 'number', 'unit' => 'rem',
                'default' => 4.5, 'min' => 1, 'max' => 10, 'step' => 0.25,
                'css' => '--s-gutter',
            ],
        ],

        'Type' => [
            'text_size' => [
                'label' => 'Body size', 'type' => 'number', 'unit' => 'rem',
                'default' => 1, 'min' => 0.75, 'max' => 1.5, 'step' => 0.0625,
                'css' => '--s-text',
            ],
            'line_height' => [
                'label' => 'Line height', 'type' => 'number', 'unit' => '',
                'default' => 1.65, 'min' => 1.1, 'max' => 2.2, 'step' => 0.05,
                'css' => '--s-leading',
            ],
            'type_scale' => [
                'label' => 'Heading scale', 'type' => 'number', 'unit' => '',
                'default' => 1.33, 'min' => 1.05, 'max' => 1.8, 'step' => 0.01,
                // Used by style_css() to derive --s-h1 to --s-h3, and exposed as a
                // variable so a future block can reference it directly.
                'css' => '--s-scale',
                'help' => 'Each heading level is this many times the one below it.',
            ],
            'heading_weight' => [
                'label' => 'Heading weight', 'type' => 'number', 'unit' => '',
                'default' => 400, 'min' => 100, 'max' => 900, 'step' => 100,
                'css' => '--s-headweight',
            ],
            'measure' => [
                'label' => 'Reading width', 'type' => 'number', 'unit' => 'ch',
                'default' => 66, 'min' => 40, 'max' => 110, 'step' => 1,
                'css' => '--s-measure',
            ],
        ],

        'Colour' => [
            'accent' => [
                'label' => 'Accent', 'type' => 'colour', 'default' => '#7257B7',
                'css' => '--accent',
            ],
            'accent_deep' => [
                'label' => 'Accent, pressed', 'type' => 'colour', 'default' => '#4A3382',
                'css' => '--accent-deep',
            ],
            'accent_tint' => [
                'label' => 'Accent tint', 'type' => 'colour', 'default' => '#F3EFFA',
                'css' => '--accent-tint',
            ],
            'mark' => [
                'label' => 'Wordmark', 'type' => 'colour', 'default' => '#CC4767',
                'css' => '--mark',
            ],
            'ink' => [
                'label' => 'Text', 'type' => 'colour', 'default' => '#1F1F1F',
                'css' => '--ink',
            ],
            'page' => [
                'label' => 'Page background', 'type' => 'colour', 'default' => '#FFFFFF',
                'css' => '--page',
            ],
            'raised' => [
                'label' => 'Panel background', 'type' => 'colour', 'default' => '#FAFAF9',
                'css' => '--raised',
            ],
        ],

        'Buttons' => [
            'cta_style' => [
                'label' => 'Style', 'type' => 'select', 'default' => 'filled',
                'options' => ['filled' => 'Filled', 'outlined' => 'Outlined', 'text' => 'Text only'],
                'body' => 'cta',
            ],
            'cta_radius' => [
                'label' => 'Corner radius', 'type' => 'number', 'unit' => 'px',
                'default' => 999, 'min' => 0, 'max' => 999, 'step' => 1,
                'css' => '--s-cta-radius',
                'presets' => ['Square' => 0, 'Match shape' => 6, 'Pill' => 999],
            ],
            'cta_pad_y' => [
                'label' => 'Padding, vertical', 'type' => 'number', 'unit' => 'rem',
                'default' => 0.625, 'min' => 0, 'max' => 2, 'step' => 0.0625,
                'css' => '--s-cta-pad-y',
            ],
            'cta_pad_x' => [
                'label' => 'Padding, horizontal', 'type' => 'number', 'unit' => 'rem',
                'default' => 1.25, 'min' => 0, 'max' => 4, 'step' => 0.0625,
                'css' => '--s-cta-pad-x',
            ],
        ],

        'Panels and swatches' => [
            'card_style' => [
                'label' => 'Panel style', 'type' => 'select', 'default' => 'flat',
                'options' => ['flat' => 'Flat fill', 'outlined' => 'Outlined', 'shadow' => 'Soft shadow'],
                'body' => 'card',
            ],
            'swatch_layout' => [
                'label' => 'Swatch layout', 'type' => 'select', 'default' => 'list',
                'options' => ['list' => 'List', 'cards' => 'Cards', 'grid' => 'Grid'],
                // Resolved into a class by block_option(), so no body attribute.
            ],
            'swatch_shape' => [
                'label' => 'Swatch shape', 'type' => 'select', 'default' => 'square',
                'options' => ['square' => 'Square', 'rounded' => 'Rounded', 'circle' => 'Circle'],
            ],
            'swatch_size' => [
                'label' => 'Swatch size', 'type' => 'number', 'unit' => 'rem',
                'default' => 6, 'min' => 2, 'max' => 16, 'step' => 0.25,
                'css' => '--s-swatch',
            ],
        ],
    ];
}

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

/** The stored value for a field, or its default, always valid. */
function style_value(string $key): string
{
    $field = style_flat()[$key] ?? null;
    if (!$field) {
        return '';
    }
    $stored = (string) setting('style.' . $key, '');
    return style_clean($field, $stored);
}

/** Validates one value against its field, falling back to the default. */
function style_clean(array $field, string $value): string
{
    $value = trim($value);

    if ($field['type'] === 'select') {
        return isset($field['options'][$value]) ? $value : (string) $field['default'];
    }

    if ($field['type'] === 'colour') {
        return preg_match('/^#[0-9a-fA-F]{6}$/', $value) ? strtoupper($value) : (string) $field['default'];
    }

    // Numbers: must parse, and are clamped to the field's own range.
    if (!is_numeric($value)) {
        return (string) $field['default'];
    }
    $number = (float) $value;
    $number = max((float) $field['min'], min((float) $field['max'], $number));

    // Trim trailing zeroes so 6.00 stores as 6.
    return rtrim(rtrim(number_format($number, 4, '.', ''), '0'), '.') ?: '0';
}

function style_save(string $key, string $value): void
{
    $field = style_flat()[$key] ?? null;
    if ($field) {
        setting_save('style.' . $key, style_clean($field, $value));
    }
}

/** A value with its unit, ready for CSS. */
function style_css_value(string $key): string
{
    $field = style_flat()[$key] ?? null;
    if (!$field) {
        return '';
    }
    $value = style_value($key);
    return $field['type'] === 'number' ? $value . ($field['unit'] ?? '') : $value;
}

/**
 * The custom properties for the current settings. Heading sizes are derived
 * from the body size and the scale, so changing either moves the whole ladder.
 */
function style_css(): string
{
    $lines = [];
    foreach (style_flat() as $key => $field) {
        if (empty($field['css'])) {
            continue;
        }
        $lines[] = '  ' . $field['css'] . ': ' . style_css_value($key) . ';';
    }

    $base  = (float) style_value('text_size');
    $scale = (float) style_value('type_scale');
    if ($base > 0 && $scale > 1) {
        // Three steps up from the body size, rounded to something sane.
        foreach ([1 => 3, 2 => 2, 3 => 1] as $level => $steps) {
            $size = $base * ($scale ** $steps);
            $lines[] = sprintf('  --s-h%d: %srem;', $level, rtrim(rtrim(number_format($size, 3, '.', ''), '0'), '.'));
        }
    }

    return $lines ? ":root {\n" . implode("\n", $lines) . "\n}" : '';
}

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
