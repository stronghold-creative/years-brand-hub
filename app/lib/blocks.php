<?php
declare(strict_types=1);

/**
 * Every block type is defined once, here. The admin builds its forms from this
 * array and the front end renders from app/blocks/<type>.php. Adding a block
 * means adding an entry here and a matching render file. No migration needed,
 * because block data lives as JSON on the page row.
 *
 * Field types: text, textarea, longtext, colour, select, repeater
 */
function block_types(): array
{
    return [
        'rich_text' => [
            'label'  => 'Text',
            'hint'   => 'Plain paragraphs. Blank line starts a new paragraph.',
            'fields' => [
                'heading' => ['type' => 'text', 'label' => 'Heading', 'optional' => true],
                'body'    => ['type' => 'longtext', 'label' => 'Body'],
            ],
        ],

        'colour_palette' => [
            'label'  => 'Colour palette',
            'hint'   => 'Swatches with full values and usage notes.',
            'fields' => [
                'heading' => ['type' => 'text', 'label' => 'Heading', 'optional' => true],
                'colours' => [
                    'type'   => 'repeater',
                    'label'  => 'Colours',
                    'item'   => 'Colour',
                    'fields' => [
                        'name'    => ['type' => 'text',     'label' => 'Name'],
                        'hex'     => ['type' => 'colour',   'label' => 'Hex'],
                        'cmyk'    => ['type' => 'text',     'label' => 'CMYK', 'optional' => true],
                        'pantone' => ['type' => 'text',     'label' => 'Pantone', 'optional' => true],
                        'usage'   => ['type' => 'textarea', 'label' => 'Where to use it', 'optional' => true],
                    ],
                ],
            ],
        ],

        'type_specimen' => [
            'label'  => 'Type specimen',
            'hint'   => 'Shows a style at its real size, with the spec beside it.',
            'fields' => [
                'heading' => ['type' => 'text', 'label' => 'Heading', 'optional' => true],
                'styles'  => [
                    'type'   => 'repeater',
                    'label'  => 'Styles',
                    'item'   => 'Style',
                    'fields' => [
                        'name'        => ['type' => 'text', 'label' => 'Style name'],
                        'family'      => ['type' => 'text', 'label' => 'Typeface'],
                        'weight'      => ['type' => 'text', 'label' => 'Weight'],
                        'size'        => ['type' => 'text', 'label' => 'Size'],
                        'line_height' => ['type' => 'text', 'label' => 'Line height', 'optional' => true],
                        'tracking'    => ['type' => 'text', 'label' => 'Tracking', 'optional' => true],
                        'sample'      => ['type' => 'text', 'label' => 'Sample text'],
                        'use_for'     => ['type' => 'textarea', 'label' => 'Use for', 'optional' => true],
                    ],
                ],
            ],
        ],

        'do_dont' => [
            'label'  => 'Do and do not',
            'hint'   => 'Paired guidance. Keep each line to one rule.',
            'fields' => [
                'heading' => ['type' => 'text', 'label' => 'Heading', 'optional' => true],
                'pairs'   => [
                    'type'   => 'repeater',
                    'label'  => 'Rules',
                    'item'   => 'Rule',
                    'fields' => [
                        'stance' => ['type' => 'select', 'label' => 'Type', 'options' => ['do' => 'Do', 'dont' => 'Do not']],
                        'rule'   => ['type' => 'text', 'label' => 'Rule'],
                        'why'    => ['type' => 'textarea', 'label' => 'Why', 'optional' => true],
                    ],
                ],
            ],
        ],

        'image' => [
            'label'  => 'Image',
            'hint'   => 'One image from the asset library, with a caption. Replacing the file updates this automatically.',
            'fields' => [
                'asset'   => ['type' => 'asset', 'label' => 'Image'],
                'alt'     => ['type' => 'text', 'label' => 'Alt text, describing the image for screen readers'],
                'caption' => ['type' => 'text', 'label' => 'Caption', 'optional' => true],
                'width'   => ['type' => 'select', 'label' => 'Width', 'options' => [
                    'text' => 'Text width',
                    'wide' => 'Wider than the text',
                    'half' => 'Half width',
                ]],
                'frame'   => ['type' => 'select', 'label' => 'Background', 'options' => [
                    'none'  => 'None',
                    'light' => 'Light panel',
                    'dark'  => 'Dark panel',
                ]],
            ],
        ],

        'image_grid' => [
            'label'  => 'Image grid',
            'hint'   => 'Several images side by side. Good for do and do not comparisons, or a set of examples.',
            'fields' => [
                'heading' => ['type' => 'text', 'label' => 'Heading', 'optional' => true],
                'columns' => ['type' => 'select', 'label' => 'Columns', 'options' => [
                    '2' => 'Two',
                    '3' => 'Three',
                    '4' => 'Four',
                ]],
                'items' => [
                    'type'   => 'repeater',
                    'label'  => 'Images',
                    'item'   => 'Image',
                    'fields' => [
                        'asset'   => ['type' => 'asset', 'label' => 'Image'],
                        'alt'     => ['type' => 'text', 'label' => 'Alt text'],
                        'caption' => ['type' => 'text', 'label' => 'Caption', 'optional' => true],
                        'stance'  => ['type' => 'select', 'label' => 'Mark as', 'options' => [
                            'none' => 'Nothing',
                            'do'   => 'Do',
                            'dont' => 'Do not',
                        ]],
                    ],
                ],
            ],
        ],

        'comparison_table' => [
            'label'  => 'Comparison table',
            'hint'   => 'Two columns, for this-not-that guidance. Words we use against words we avoid, for example.',
            'fields' => [
                'heading' => ['type' => 'text', 'label' => 'Heading', 'optional' => true],
                'left'    => ['type' => 'text', 'label' => 'Left column heading'],
                'right'   => ['type' => 'text', 'label' => 'Right column heading'],
                'rows'    => [
                    'type'   => 'repeater',
                    'label'  => 'Rows',
                    'item'   => 'Row',
                    'fields' => [
                        'left'  => ['type' => 'text', 'label' => 'Left'],
                        'right' => ['type' => 'text', 'label' => 'Right'],
                        'note'  => ['type' => 'text', 'label' => 'Note', 'optional' => true],
                    ],
                ],
            ],
        ],

        'download_list' => [
            'label'  => 'Downloads',
            'hint'   => 'Lists every asset carrying a tag, so uploading a new file adds it here automatically.',
            'fields' => [
                'heading'   => ['type' => 'text', 'label' => 'Heading', 'optional' => true],
                'tag'       => ['type' => 'text', 'label' => 'Asset tag, for example: logo'],
                'intro'     => ['type' => 'textarea', 'label' => 'Intro', 'optional' => true],
                'bundle'    => ['type' => 'select', 'label' => 'Download all button', 'options' => [
                    'yes' => 'Show it',
                    'no'  => 'Hide it',
                ]],
            ],
        ],

        'callout' => [
            'label'  => 'Callout',
            'hint'   => 'One thing people keep getting wrong.',
            'fields' => [
                'tone' => ['type' => 'select', 'label' => 'Tone', 'options' => [
                    'note'    => 'Note',
                    'warning' => 'Watch out',
                ]],
                'body' => ['type' => 'textarea', 'label' => 'Body'],
            ],
        ],
    ];
}

/**
 * Options specific to one block type: how that block presents its own content.
 * Every option starts with "inherit", meaning take the site default, so a
 * global change reaches every block that has not been deliberately overridden.
 */
function block_options(string $type): array
{
    $sets = [
        'rich_text' => [
            'size'  => ['label' => 'Text size', 'options' => [
                'inherit' => 'Site default', 'small' => 'Small', 'normal' => 'Normal',
                'large' => 'Large', 'lead' => 'Lead paragraph',
            ]],
            'align' => ['label' => 'Alignment', 'options' => [
                'left' => 'Left', 'centre' => 'Centred',
            ]],
            'columns' => ['label' => 'Columns', 'options' => [
                'one' => 'One', 'two' => 'Two',
            ]],
        ],
        'colour_palette' => [
            'layout' => ['label' => 'Layout', 'options' => [
                'inherit' => 'Site default', 'list' => 'List', 'cards' => 'Cards', 'grid' => 'Grid',
            ]],
            'shape' => ['label' => 'Swatch shape', 'options' => [
                'inherit' => 'Site default', 'square' => 'Square', 'rounded' => 'Rounded', 'circle' => 'Circle',
            ]],
            'size' => ['label' => 'Swatch size', 'options' => [
                'small' => 'Small', 'medium' => 'Medium', 'large' => 'Large',
            ]],
            'values' => ['label' => 'Show', 'options' => [
                'all' => 'Every value', 'hex' => 'Hex only', 'none' => 'Name only',
            ]],
        ],
        'type_specimen' => [
            'spec' => ['label' => 'Specification', 'options' => [
                'beside' => 'Beside the sample', 'under' => 'Under the sample', 'hidden' => 'Hidden',
            ]],
        ],
        'do_dont' => [
            'layout' => ['label' => 'Layout', 'options' => [
                'rows' => 'Rows', 'columns' => 'Two columns',
            ]],
        ],
        'image' => [
            'fit' => ['label' => 'Image fit', 'options' => [
                'natural' => 'Natural height', 'square' => 'Square', 'wide' => 'Wide crop',
            ]],
            'radius' => ['label' => 'Corners', 'options' => [
                'inherit' => 'Site default', 'none' => 'Square', 'round' => 'Rounded',
            ]],
        ],
        'image_grid' => [
            'gap' => ['label' => 'Space between', 'options' => [
                'tight' => 'Tight', 'normal' => 'Normal', 'loose' => 'Loose',
            ]],
            'fit' => ['label' => 'Image fit', 'options' => [
                'natural' => 'Natural height', 'square' => 'Square', 'wide' => 'Wide crop',
            ]],
        ],
        'comparison_table' => [
            'density' => ['label' => 'Row height', 'options' => [
                'compact' => 'Compact', 'normal' => 'Normal',
            ]],
            'strike' => ['label' => 'Right column', 'options' => [
                'struck' => 'Struck through', 'plain' => 'Plain',
            ]],
        ],
        'download_list' => [
            'layout' => ['label' => 'Layout', 'options' => [
                'list' => 'List', 'cards' => 'Cards',
            ]],
        ],
        'callout' => [
            'emphasis' => ['label' => 'Emphasis', 'options' => [
                'bar' => 'Side bar', 'filled' => 'Filled panel', 'quiet' => 'Quiet',
            ]],
        ],
    ];
    return $sets[$type] ?? [];
}

function block_options_defaults(string $type): array
{
    $out = [];
    foreach (block_options($type) as $key => $field) {
        $out[$key] = (string) array_key_first($field['options']);
    }
    return $out;
}

/**
 * Resolves one option, following "inherit" to the matching site setting.
 * $siteKey names the global setting this option inherits from.
 */
function block_option(array $options, string $key, string $fallback, ?string $siteKey = null): string
{
    $value = (string) ($options[$key] ?? 'inherit');
    if ($value !== 'inherit' && $value !== '') {
        return $value;
    }
    if ($siteKey !== null && function_exists('style_value')) {
        $site = style_value($siteKey);
        if ($site !== '') {
            return $site;
        }
    }
    return $fallback;
}

/**
 * Settings every block carries, separate from its content. These control how a
 * block looks and sits on the page rather than what it says, so they are
 * defined once here instead of being repeated in every block type.
 */
function block_settings_fields(): array
{
    return [
        'width' => ['type' => 'select', 'label' => 'Width', 'options' => [
            'text' => 'Text column',
            'wide' => 'Wider',
            'full' => 'Full width',
        ]],
        'background' => ['type' => 'select', 'label' => 'Background', 'options' => [
            'none'   => 'None',
            'tint'   => 'Soft tint',
            'purple' => 'Purple',
            'dark'   => 'Dark',
        ]],
        'spacing' => ['type' => 'select', 'label' => 'Space below', 'options' => [
            'normal' => 'Normal',
            'tight'  => 'Tight',
            'loose'  => 'Loose',
        ]],
        'divider' => ['type' => 'select', 'label' => 'Rule above', 'options' => [
            'no'  => 'No',
            'yes' => 'Yes',
        ]],
        'anchor' => ['type' => 'text', 'label' => 'Link anchor', 'optional' => true],
    ];
}

function block_settings_defaults(): array
{
    $out = [];
    foreach (block_settings_fields() as $key => $field) {
        $out[$key] = $field['type'] === 'select' ? (string) array_key_first($field['options']) : '';
    }
    return $out;
}

function block_defaults(string $type): array
{
    $def = block_types()[$type] ?? null;
    if (!$def) {
        return [];
    }
    $data = [];
    foreach ($def['fields'] as $key => $field) {
        $data[$key] = match ($field['type']) {
            'repeater' => [],
            'select'   => (string) array_key_first($field['options']),
            'colour'   => '#7257B7',
            'asset'    => '',
            default    => '',
        };
    }
    return $data;
}

/** Strip anything not described by the definition, so stored JSON stays clean. */
function sanitise_blocks(array $blocks): array
{
    $types = block_types();
    $clean = [];

    foreach ($blocks as $block) {
        $type = $block['type'] ?? '';
        if (!isset($types[$type])) {
            continue;
        }
        $data = [];
        foreach ($types[$type]['fields'] as $key => $field) {
            $value = $block['data'][$key] ?? null;
            if ($field['type'] === 'repeater') {
                $rows = [];
                foreach (is_array($value) ? $value : [] as $row) {
                    $clean_row = [];
                    foreach ($field['fields'] as $subKey => $subField) {
                        $value = $row[$subKey] ?? '';
                        if ($subField['type'] === 'asset') {
                            $clean_row[$subKey] = (string) (int) $value;
                        } elseif ($subField['type'] === 'select') {
                            $clean_row[$subKey] = (string) (isset($subField['options'][$value]) ? $value : array_key_first($subField['options']));
                        } else {
                            $clean_row[$subKey] = trim((string) $value);
                        }
                    }
                    $rows[] = $clean_row;
                }
                $data[$key] = $rows;
            } elseif ($field['type'] === 'select') {
                // Cast to string: PHP turns numeric option keys into integers,
                // and stored JSON should hold one consistent type.
                $data[$key] = (string) (isset($field['options'][$value]) ? $value : array_key_first($field['options']));
            } elseif ($field['type'] === 'asset') {
                // Store the id only. A missing or bogus id renders as nothing.
                $data[$key] = (string) (int) $value;
            } else {
                $data[$key] = trim((string) $value);
            }
        }
        $settings = [];
        foreach (block_settings_fields() as $key => $field) {
            $value = $block['settings'][$key] ?? null;
            if ($field['type'] === 'select') {
                $settings[$key] = (string) (isset($field['options'][$value]) ? $value : array_key_first($field['options']));
            } else {
                // Anchors end up in a URL, so only safe characters survive.
                $settings[$key] = strtolower(preg_replace('/[^A-Za-z0-9\-]/', '', (string) $value) ?? '');
            }
        }

        $options = [];
        foreach (block_options($type) as $key => $field) {
            $value = $block['options'][$key] ?? null;
            $options[$key] = (string) (isset($field['options'][$value]) ? $value : array_key_first($field['options']));
        }

        $clean[] = ['type' => $type, 'data' => $data, 'settings' => $settings, 'options' => $options];
    }

    return $clean;
}

function render_blocks(array $blocks): void
{
    foreach ($blocks as $block) {
        $file = APP_DIR . '/blocks/' . basename((string) $block['type']) . '.php';
        if (!is_file($file)) {
            continue;
        }
        $data     = $block['data'] ?? [];
        $options  = array_merge(block_options_defaults((string) $block['type']), $block['options'] ?? []);
        $settings = array_merge(block_settings_defaults(), $block['settings'] ?? []);

        $classes = [
            'zone',
            'zone--w-' . $settings['width'],
            'zone--bg-' . $settings['background'],
            'zone--sp-' . $settings['spacing'],
        ];
        if (($settings['divider'] ?? 'no') === 'yes') {
            $classes[] = 'zone--ruled';
        }

        printf(
            '<div class="%s"%s>',
            e(implode(' ', $classes)),
            $settings['anchor'] !== '' ? ' id="' . e($settings['anchor']) . '"' : ''
        );
        require $file;
        echo '</div>';
    }
}

/** Turn plain text with blank lines into paragraphs. No HTML passes through. */
function paragraphs(string $text): string
{
    $parts = preg_split("/\n\s*\n/", trim($text)) ?: [];
    $out = '';
    foreach ($parts as $part) {
        $out .= '<p>' . nl2br(e(trim($part))) . '</p>';
    }
    return $out;
}
