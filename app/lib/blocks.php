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
            'select'   => array_key_first($field['options']),
            'colour'   => '#7257B7',
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
                        $clean_row[$subKey] = trim((string) ($row[$subKey] ?? ''));
                    }
                    $rows[] = $clean_row;
                }
                $data[$key] = $rows;
            } elseif ($field['type'] === 'select') {
                $data[$key] = isset($field['options'][$value]) ? $value : array_key_first($field['options']);
            } else {
                $data[$key] = trim((string) $value);
            }
        }
        $clean[] = ['type' => $type, 'data' => $data];
    }

    return $clean;
}

function render_blocks(array $blocks): void
{
    foreach ($blocks as $block) {
        $file = APP_DIR . '/blocks/' . basename((string) $block['type']) . '.php';
        if (is_file($file)) {
            $data = $block['data'] ?? [];
            require $file;
        }
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
