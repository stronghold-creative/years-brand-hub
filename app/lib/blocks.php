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

        // ------------------------------------------------ documentation blocks

        'clearspace' => [
            'label'  => 'Clearspace diagram',
            'hint'   => 'A mark with its minimum clear space drawn around it.',
            'fields' => [
                'heading' => ['type' => 'text',   'label' => 'Heading', 'optional' => true],
                'asset'   => ['type' => 'asset',  'label' => 'Mark'],
                'measure' => ['type' => 'text',   'label' => 'Clear space equals', 'optional' => true],
                'ratio'   => ['type' => 'select', 'label' => 'Space shown', 'options' => [
                    '25' => 'A quarter of the mark', '50' => 'Half the mark', '100' => 'The full mark',
                ]],
                'note'    => ['type' => 'textarea', 'label' => 'Note', 'optional' => true],
            ],
        ],

        'spacing_scale' => [
            'label'  => 'Spacing scale',
            'hint'   => 'Each step drawn at its real size, with its value.',
            'fields' => [
                'heading' => ['type' => 'text', 'label' => 'Heading', 'optional' => true],
                'steps'   => [
                    'type' => 'repeater', 'label' => 'Steps', 'item' => 'Step',
                    'fields' => [
                        'name'  => ['type' => 'text', 'label' => 'Name'],
                        'value' => ['type' => 'text', 'label' => 'Value, for example 16px'],
                        'use'   => ['type' => 'text', 'label' => 'Used for', 'optional' => true],
                    ],
                ],
            ],
        ],

        'icon_grid' => [
            'label'  => 'Icon grid',
            'hint'   => 'Every asset carrying a tag, shown as a grid you can copy from.',
            'fields' => [
                'heading' => ['type' => 'text',   'label' => 'Heading', 'optional' => true],
                'tag'     => ['type' => 'text',   'label' => 'Asset tag'],
                'note'    => ['type' => 'textarea', 'label' => 'Note', 'optional' => true],
            ],
        ],

        'contrast_pairs' => [
            'label'  => 'Contrast pairs',
            'hint'   => 'Colour combinations with their measured contrast ratio.',
            'fields' => [
                'heading' => ['type' => 'text', 'label' => 'Heading', 'optional' => true],
                'pairs'   => [
                    'type' => 'repeater', 'label' => 'Pairs', 'item' => 'Pair',
                    'fields' => [
                        'label'      => ['type' => 'text',   'label' => 'Name', 'optional' => true],
                        'foreground' => ['type' => 'colour', 'label' => 'Text'],
                        'background' => ['type' => 'colour', 'label' => 'Background'],
                        'sample'     => ['type' => 'text',   'label' => 'Sample text', 'optional' => true],
                    ],
                ],
            ],
        ],

        'misuse_gallery' => [
            'label'  => 'Correct and incorrect',
            'hint'   => 'Images marked right or wrong, with a reason.',
            'fields' => [
                'heading' => ['type' => 'text', 'label' => 'Heading', 'optional' => true],
                'items'   => [
                    'type' => 'repeater', 'label' => 'Examples', 'item' => 'Example',
                    'fields' => [
                        'asset'   => ['type' => 'asset',  'label' => 'Image'],
                        'stance'  => ['type' => 'select', 'label' => 'Verdict', 'options' => ['do' => 'Correct', 'dont' => 'Incorrect']],
                        'caption' => ['type' => 'text',   'label' => 'Why', 'optional' => true],
                    ],
                ],
            ],
        ],

        'spec_table' => [
            'label'  => 'Specification',
            'hint'   => 'Plain label and value rows.',
            'fields' => [
                'heading' => ['type' => 'text', 'label' => 'Heading', 'optional' => true],
                'rows'    => [
                    'type' => 'repeater', 'label' => 'Rows', 'item' => 'Row',
                    'fields' => [
                        'label' => ['type' => 'text', 'label' => 'Label'],
                        'value' => ['type' => 'text', 'label' => 'Value'],
                        'note'  => ['type' => 'text', 'label' => 'Note', 'optional' => true],
                    ],
                ],
            ],
        ],

        'link_list' => [
            'label'  => 'Links',
            'hint'   => 'Somewhere else to go: Figma, Drive, a tool.',
            'fields' => [
                'heading' => ['type' => 'text', 'label' => 'Heading', 'optional' => true],
                'links'   => [
                    'type' => 'repeater', 'label' => 'Links', 'item' => 'Link',
                    'fields' => [
                        'label' => ['type' => 'text', 'label' => 'Label'],
                        'url'   => ['type' => 'text', 'label' => 'Address'],
                        'note'  => ['type' => 'text', 'label' => 'Note', 'optional' => true],
                    ],
                ],
            ],
        ],

        'contacts' => [
            'label'  => 'Who to ask',
            'hint'   => 'People, what they cover, and how to reach them.',
            'fields' => [
                'heading' => ['type' => 'text', 'label' => 'Heading', 'optional' => true],
                'people'  => [
                    'type' => 'repeater', 'label' => 'People', 'item' => 'Person',
                    'fields' => [
                        'name'  => ['type' => 'text', 'label' => 'Name'],
                        'role'  => ['type' => 'text', 'label' => 'Covers', 'optional' => true],
                        'email' => ['type' => 'text', 'label' => 'Email', 'optional' => true],
                        'slack' => ['type' => 'text', 'label' => 'Slack channel', 'optional' => true],
                    ],
                ],
            ],
        ],

        'checklist' => [
            'label'  => 'Checklist',
            'hint'   => 'Things to confirm before something ships.',
            'fields' => [
                'heading' => ['type' => 'text', 'label' => 'Heading', 'optional' => true],
                'items'   => [
                    'type' => 'repeater', 'label' => 'Items', 'item' => 'Item',
                    'fields' => [
                        'item' => ['type' => 'text',     'label' => 'Check'],
                        'note' => ['type' => 'textarea', 'label' => 'Detail', 'optional' => true],
                    ],
                ],
            ],
        ],

        'video' => [
            'label'  => 'Video',
            'hint'   => 'A YouTube or Vimeo link, or an uploaded file.',
            'fields' => [
                'heading' => ['type' => 'text',  'label' => 'Heading', 'optional' => true],
                'url'     => ['type' => 'text',  'label' => 'YouTube or Vimeo address', 'optional' => true],
                'asset'   => ['type' => 'asset', 'label' => 'Or an uploaded file', 'optional' => true],
                'caption' => ['type' => 'text',  'label' => 'Caption', 'optional' => true],
            ],
        ],

        // -------------------------------------------------- presentation blocks

        'tabs' => [
            'label'  => 'Tabs',
            'hint'   => 'Variants of one thing in a single space.',
            'fields' => [
                'heading' => ['type' => 'text', 'label' => 'Heading', 'optional' => true],
                'panels'  => [
                    'type' => 'repeater', 'label' => 'Tabs', 'item' => 'Tab',
                    'fields' => [
                        'label' => ['type' => 'text',     'label' => 'Tab label'],
                        'body'  => ['type' => 'longtext', 'label' => 'Body', 'optional' => true],
                        'asset' => ['type' => 'asset',    'label' => 'Image', 'optional' => true],
                    ],
                ],
            ],
        ],

        'accordion' => [
            'label'  => 'Accordion',
            'hint'   => 'Long reference that would otherwise swamp the page.',
            'fields' => [
                'heading' => ['type' => 'text', 'label' => 'Heading', 'optional' => true],
                'items'   => [
                    'type' => 'repeater', 'label' => 'Entries', 'item' => 'Entry',
                    'fields' => [
                        'question' => ['type' => 'text',     'label' => 'Title'],
                        'answer'   => ['type' => 'longtext', 'label' => 'Body'],
                    ],
                ],
            ],
        ],

        'code' => [
            'label'  => 'Code',
            'hint'   => 'A snippet with a copy button. Liquid, CSS, tokens.',
            'fields' => [
                'heading'  => ['type' => 'text',     'label' => 'Heading', 'optional' => true],
                'language' => ['type' => 'select',   'label' => 'Language', 'options' => [
                    'plain' => 'Plain', 'liquid' => 'Liquid', 'html' => 'HTML', 'css' => 'CSS',
                    'js' => 'JavaScript', 'json' => 'JSON',
                ]],
                'code'     => ['type' => 'longtext', 'label' => 'Code'],
                'caption'  => ['type' => 'text',     'label' => 'Caption', 'optional' => true],
            ],
        ],

        'steps' => [
            'label'  => 'Steps',
            'hint'   => 'A numbered process, in order.',
            'fields' => [
                'heading' => ['type' => 'text', 'label' => 'Heading', 'optional' => true],
                'steps'   => [
                    'type' => 'repeater', 'label' => 'Steps', 'item' => 'Step',
                    'fields' => [
                        'title' => ['type' => 'text',     'label' => 'Title'],
                        'body'  => ['type' => 'textarea', 'label' => 'Detail', 'optional' => true],
                    ],
                ],
            ],
        ],

        'hero' => [
            'label'  => 'Hero',
            'hint'   => 'A full-bleed opener. Each line is sized to fill the width.',
            'fields' => [
                'label'   => ['type' => 'text',     'label' => 'Small label above', 'optional' => true],
                'lines'   => ['type' => 'longtext', 'label' => 'Headline, one line per line'],
                'standfirst' => ['type' => 'textarea', 'label' => 'Standfirst', 'optional' => true],
                'background' => ['type' => 'colour', 'label' => 'Background colour'],
                'ink'     => ['type' => 'colour',   'label' => 'Text colour'],
                'asset'   => ['type' => 'asset',    'label' => 'Background image', 'optional' => true],
            ],
        ],

        'quote' => [
            'label'  => 'Quote',
            'hint'   => 'A line of real copy, shown as an example.',
            'fields' => [
                'text'   => ['type' => 'longtext', 'label' => 'Quote'],
                'source' => ['type' => 'text',     'label' => 'Where it is from', 'optional' => true],
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
        'spacing_scale' => [
            'bar' => ['label' => 'Show', 'options' => ['bars' => 'Bars at real size', 'plain' => 'Values only']],
        ],
        'icon_grid' => [
            'size' => ['label' => 'Icon size', 'options' => ['small' => 'Small', 'medium' => 'Medium', 'large' => 'Large']],
            'names' => ['label' => 'Names', 'options' => ['show' => 'Show', 'hide' => 'Hide']],
        ],
        'contrast_pairs' => [
            'layout' => ['label' => 'Layout', 'options' => ['cards' => 'Cards', 'rows' => 'Rows']],
        ],
        'misuse_gallery' => [
            'columns' => ['label' => 'Columns', 'options' => ['two' => 'Two', 'three' => 'Three', 'one' => 'One']],
        ],
        'spec_table' => [
            'density' => ['label' => 'Row height', 'options' => ['normal' => 'Normal', 'compact' => 'Compact']],
        ],
        'link_list' => [
            'layout' => ['label' => 'Layout', 'options' => ['rows' => 'Rows', 'cards' => 'Cards']],
        ],
        'contacts' => [
            'layout' => ['label' => 'Layout', 'options' => ['cards' => 'Cards', 'rows' => 'Rows']],
        ],
        'checklist' => [
            'style' => ['label' => 'Marker', 'options' => ['box' => 'Check box', 'tick' => 'Tick', 'number' => 'Number']],
        ],
        'tabs' => [
            'style' => ['label' => 'Tab style', 'options' => ['underline' => 'Underline', 'pill' => 'Pills']],
        ],
        'steps' => [
            'style' => ['label' => 'Marker', 'options' => ['number' => 'Numbered', 'line' => 'Numbered with a line']],
        ],
        'hero' => [
            'label'  => 'Hero',
            'hint'   => 'A full-bleed opener. Each line is sized to fill the width.',
            'fields' => [
                'label'   => ['type' => 'text',     'label' => 'Small label above', 'optional' => true],
                'lines'   => ['type' => 'longtext', 'label' => 'Headline, one line per line'],
                'standfirst' => ['type' => 'textarea', 'label' => 'Standfirst', 'optional' => true],
                'background' => ['type' => 'colour', 'label' => 'Background colour'],
                'ink'     => ['type' => 'colour',   'label' => 'Text colour'],
                'asset'   => ['type' => 'asset',    'label' => 'Background image', 'optional' => true],
            ],
        ],

        'quote' => [
            'size' => ['label' => 'Size', 'options' => ['normal' => 'Normal', 'large' => 'Large']],
        ],
        'hero' => [
            'height' => ['label' => 'Height', 'options' => [
                'full' => 'Full screen', 'tall' => 'Two thirds', 'natural' => 'Natural',
            ]],
            'align' => ['label' => 'Alignment', 'options' => [
                'centre' => 'Centred', 'left' => 'Left', 'bottom' => 'Bottom left',
            ]],
            'case' => ['label' => 'Letter case', 'options' => [
                'upper' => 'Uppercase', 'as-typed' => 'As typed',
            ]],
            'reveal' => ['label' => 'Reveal', 'options' => [
                'rise' => 'Lines rise', 'clip' => 'Lines wipe up', 'none' => 'None',
            ]],
            'overlay' => ['label' => 'Image darkening', 'options' => [
                'none' => 'None', 'soft' => 'Soft', 'strong' => 'Strong',
            ]],
        ],
        'code' => [
            'wrap' => ['label' => 'Long lines', 'options' => ['scroll' => 'Scroll sideways', 'wrap' => 'Wrap']],
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

/**
 * A colour value that is safe to put in a style attribute.
 *
 * Only a six or three digit hex survives. Escaping alone is not enough here:
 * a value like "red; display: none" cannot break out of the attribute, but it
 * can still inject further declarations onto that element.
 */
function sanitise_colour(string $value, string $fallback = ''): string
{
    $value = trim($value);
    return preg_match('/^#([0-9a-fA-F]{3}|[0-9a-fA-F]{6})$/', $value) ? strtoupper($value) : $fallback;
}

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
                        } elseif ($subField['type'] === 'colour') {
                            $clean_row[$subKey] = sanitise_colour((string) $value);
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
            } elseif ($field['type'] === 'colour') {
                $data[$key] = sanitise_colour((string) $value);
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

/**
 * Renders the blocks of a page. When $editable is true each block is tagged
 * with its index, so the editor's canvas can map a click back to a block.
 * The markup is otherwise identical, so the preview is a true preview.
 */
function render_blocks(array $blocks, bool $editable = false): void
{
    foreach ($blocks as $index => $block) {
        $file = APP_DIR . '/blocks/' . basename((string) $block['type']) . '.php';
        if (!is_file($file)) {
            continue;
        }
        $data     = $block['data'] ?? [];
        $options  = array_merge(block_options_defaults((string) $block['type']), $block['options'] ?? []);
        $settings = array_merge(block_settings_defaults(), $block['settings'] ?? []);

        // A hero is always edge to edge and carries its own background, so
        // the placement settings for width, background and spacing do not
        // apply to it and would only fight its own layout.
        $isHero = ($block['type'] ?? '') === 'hero';

        $classes = $isHero
            ? ['zone', 'zone--w-screen', 'zone--bg-none', 'zone--sp-none']
            : [
                'zone',
                'zone--w-' . $settings['width'],
                'zone--bg-' . $settings['background'],
                'zone--sp-' . $settings['spacing'],
            ];
        if (($settings['divider'] ?? 'no') === 'yes') {
            $classes[] = 'zone--ruled';
        }

        printf(
            '<div class="%s"%s%s>',
            e(implode(' ', $classes)),
            $settings['anchor'] !== '' ? ' id="' . e($settings['anchor']) . '"' : '',
            $editable ? ' data-block="' . (int) $index . '"' : ''
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

/* ===========================================================================
   Contrast

   WCAG relative luminance and ratio, so a contrast block states a measured
   fact rather than an opinion.
   =========================================================================== */

function colour_luminance(string $hex): float
{
    $hex = ltrim(trim($hex), '#');
    if (strlen($hex) === 3) {
        $hex = $hex[0] . $hex[0] . $hex[1] . $hex[1] . $hex[2] . $hex[2];
    }
    if (!preg_match('/^[0-9a-fA-F]{6}$/', $hex)) {
        return 0.0;
    }

    $channels = [];
    foreach ([0, 2, 4] as $offset) {
        $value = hexdec(substr($hex, $offset, 2)) / 255;
        $channels[] = $value <= 0.03928 ? $value / 12.92 : (($value + 0.055) / 1.055) ** 2.4;
    }
    return 0.2126 * $channels[0] + 0.7152 * $channels[1] + 0.0722 * $channels[2];
}

function contrast_ratio(string $foreground, string $background): float
{
    $a = colour_luminance($foreground);
    $b = colour_luminance($background);
    $light = max($a, $b);
    $dark  = min($a, $b);
    return round(($light + 0.05) / ($dark + 0.05), 2);
}

/** What a ratio passes, in WCAG terms. */
function contrast_verdict(float $ratio): array
{
    if ($ratio >= 7)   return ['level' => 'AAA', 'note' => 'Passes AAA for body text', 'pass' => true];
    if ($ratio >= 4.5) return ['level' => 'AA',  'note' => 'Passes AA for body text', 'pass' => true];
    if ($ratio >= 3)   return ['level' => 'AA large', 'note' => 'Large text and interface parts only', 'pass' => true];
    return ['level' => 'Fails', 'note' => 'Not enough contrast for text', 'pass' => false];
}

/**
 * Renders one block on its own, without the zone wrapper.
 *
 * A section opener sits outside the reading grid, so it needs no width class,
 * no background class and no spacing: it is the full width of the page by
 * virtue of where it sits, not by breaking out of something.
 */
function render_bare_block(array $block): void
{
    $type = (string) ($block['type'] ?? '');
    $file = APP_DIR . '/blocks/' . $type . '.php';
    if (!isset(block_types()[$type]) || !is_file($file)) {
        return;
    }

    $data    = $block['data'] ?? [];
    $options = array_merge(block_options_defaults($type), $block['options'] ?? []);
    require $file;
}

/**
 * Takes the opening hero off a section, if it has one.
 *
 * A hero is a page opener, so when the first page of a section begins with
 * one it is lifted out and rendered above the whole section rather than
 * halfway down a scroll beside the rail. The page keeps its remaining blocks.
 *
 * Returns the hero block, or null, and edits $pages in place.
 */
function take_section_hero(array &$pages): ?array
{
    if (!$pages) {
        return null;
    }

    $first = json_decode($pages[0]['blocks'] ?? '[]', true);
    if (!is_array($first) || !$first || ($first[0]['type'] ?? '') !== 'hero') {
        return null;
    }

    $hero = array_shift($first);
    $pages[0]['blocks'] = json_encode($first, JSON_UNESCAPED_SLASHES);
    return $hero;
}
