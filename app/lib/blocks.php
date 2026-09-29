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
/**
 * The typefaces a specimen may be set in.
 *
 * Every brand face actually on the server, then the two stacks the site itself
 * uses. A dropdown rather than a text field because a family name typed with the
 * wrong spacing or case renders the fallback and looks like the font failing to
 * load — which is exactly the kind of wrong that is invisible to whoever typed
 * it and obvious to everyone else.
 */
/**
 * Values that are safe to write into a style attribute.
 *
 * A specimen puts its size, weight, line height and tracking straight into
 * `style="..."`, so each is constrained to the shape it is meant to be rather
 * than escaped and hoped for. `e()` alone would stop a quote breaking out of the
 * attribute but would happily pass `red; background: url(...)` through as a
 * declaration. These reject anything that is not the kind of value asked for and
 * fall back to a sane default, so a typo shows as the default rather than as a
 * broken page.
 */
function spec_length(string $value, string $fallback): string
{
    $value = trim($value);
    // A number with a unit, or one of the functions type is genuinely written
    // with. Anything else, including a semicolon, is not a length.
    $ok = preg_match('/^-?[0-9.]+(px|rem|em|%|pt|vw|vh|ch|ex)?$/i', $value) === 1
       || preg_match('/^(clamp|calc|min|max)\([^;{}"\'<>]*\)$/i', $value) === 1
       || in_array(strtolower($value), ['normal', 'inherit', 'auto'], true);
    return $ok ? $value : $fallback;
}

function spec_number(string $value, string $fallback): string
{
    $value = trim($value);
    return preg_match('/^[0-9]*\.?[0-9]+(px|rem|em|%)?$/', $value) === 1 ? $value : $fallback;
}

function spec_weight(string $value): string
{
    $value = trim($value);
    if (preg_match('/^[1-9]00$/', $value) === 1) {
        return $value;
    }
    $named = ['thin' => '100', 'extralight' => '200', 'light' => '300', 'regular' => '400',
              'normal' => '400', 'book' => '400', 'medium' => '500', 'semibold' => '600',
              'bold' => '700', 'extrabold' => '800', 'black' => '900'];
    return $named[strtolower(str_replace([' ', '-'], '', $value))] ?? '400';
}

function font_options(): array
{
    $out = [];
    // Guarded rather than assumed: bootstrap.php loads every library, but the
    // checks load only what they need, and a block definition that cannot be
    // read without settings.php would make block_types() unusable on its own.
    foreach (function_exists('brand_fonts') ? array_keys(brand_fonts()) : [] as $family) {
        $out[$family] = $family;
    }
    $out['Plus Jakarta Sans'] = 'Plus Jakarta Sans (interface)';
    $out['system-ui']         = 'System sans';
    $out['Georgia, serif']    = 'System serif';
    $out['ui-monospace, monospace'] = 'System mono';
    return $out;
}

function block_types(): array
{
    return [
        // A heading on its own. Every other block can carry one, but a section
        // that needs a title above several blocks had nowhere to put it without
        // attaching it to whichever block happened to come first — which then
        // moved with that block when the order changed.
        'heading' => [
            'label'  => 'Heading',
            'hint'   => 'A title over whatever follows it. Not tied to one block.',
            'group'  => 'Text',
            'icon'   => 'heading',
            'fields' => [
                'text'    => ['type' => 'text', 'label' => 'Heading'],
                'standfirst' => ['type' => 'textarea', 'label' => 'Standfirst', 'optional' => true],
            ],
        ],

        'rich_text' => [
            'label'  => 'Text',
            'hint'   => 'Plain paragraphs. Blank line starts a new paragraph.',
            'group'  => 'Text',
            'icon'   => 'text',
            'fields' => [
                'heading' => ['type' => 'text', 'label' => 'Heading', 'optional' => true],
                'body'    => ['type' => 'longtext', 'label' => 'Body'],
            ],
        ],

        'colour_palette' => [
            'label'  => 'Colour palette',
            'hint'   => 'Swatches with full values and usage notes.',
            'group'  => 'Colour and type',
            'icon'   => 'palette',
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

        'colour_ramp' => [
            'label'  => 'Colour ramp',
            'hint'   => 'Families of colour, each with its own steps. The scale is whatever you give it.',
            'group'  => 'Colour and type',
            'icon'   => 'ramp',
            'fields' => [
                'heading'  => ['type' => 'text', 'label' => 'Heading', 'optional' => true],
                'families' => [
                    'type'   => 'repeater',
                    'label'  => 'Families',
                    'item'   => 'Family',
                    'fields' => [
                        'name'  => ['type' => 'text', 'label' => 'Family name, for example Purple'],
                        'note'  => ['type' => 'text', 'label' => 'Note', 'optional' => true],
                        // A repeater inside a repeater, so a family can have
                        // eleven steps or three, and the labels are yours.
                        'steps' => [
                            'type'   => 'repeater',
                            'label'  => 'Steps',
                            'item'   => 'Step',
                            'fields' => [
                                'step' => ['type' => 'text',   'label' => 'Step, for example 500'],
                                'hex'  => ['type' => 'colour', 'label' => 'Hex'],
                                'use'  => ['type' => 'text',   'label' => 'Used for', 'optional' => true],
                            ],
                        ],
                    ],
                ],
            ],
        ],

        'colour_story' => [
            'label'  => 'Primary and secondary',
            'hint'   => 'The brand\'s colours as one composition, sized by how much each one carries.',
            'group'  => 'Colour and type',
            'icon'   => 'story',
            'fields' => [
                'heading' => ['type' => 'text', 'label' => 'Heading', 'optional' => true],
                'intro'   => ['type' => 'textarea', 'label' => 'Intro', 'optional' => true],
                'colours' => [
                    'type'   => 'repeater',
                    'label'  => 'Colours',
                    'item'   => 'Colour',
                    'fields' => [
                        'name'    => ['type' => 'text',   'label' => 'Name'],
                        'hex'     => ['type' => 'colour', 'label' => 'Hex'],
                        'role'    => ['type' => 'select', 'label' => 'Role', 'options' => [
                            'primary'   => 'Primary',
                            'secondary' => 'Secondary',
                        ]],
                        'pantone' => ['type' => 'text', 'label' => 'Pantone', 'optional' => true],
                        'usage'   => ['type' => 'text', 'label' => 'Where to use it', 'optional' => true],
                    ],
                ],
            ],
        ],

        'type_specimen' => [
            'label'  => 'Type specimen',
            'hint'   => 'Shows a style at its real size, with the spec beside it.',
            'group'  => 'Colour and type',
            'icon'   => 'type',
            'fields' => [
                'heading' => ['type' => 'text', 'label' => 'Heading', 'optional' => true],
                'styles'  => [
                    'type'   => 'repeater',
                    'label'  => 'Styles',
                    'item'   => 'Style',
                    'fields' => [
                        'name'        => ['type' => 'text', 'label' => 'Style name'],
                        // The faces actually on the server, plus the stacks, so
                        // nobody has to remember how a family name is spelled —
                        // and a typo can no longer silently render the fallback.
                        'family'      => ['type' => 'select', 'label' => 'Typeface',
                                          'options' => font_options()],
                        'weight'      => ['type' => 'text', 'label' => 'Weight'],
                        'size'        => ['type' => 'text', 'label' => 'Size on desktop',
                                          'help' => 'With its unit: 2.5rem, 40px, clamp(2rem, 4vw, 3rem).'],
                        // Type is specified at two sizes here, so the specimen
                        // has to be able to say both. One number was a spec that
                        // only described half of what was designed.
                        'size_mobile' => ['type' => 'text', 'label' => 'Size on mobile', 'optional' => true,
                                          'help' => 'Used below 48rem. Leave blank to use the desktop size at every width.'],
                        'line_height' => ['type' => 'text', 'label' => 'Line height', 'optional' => true],
                        'tracking'    => ['type' => 'text', 'label' => 'Tracking', 'optional' => true],
                        'sample'      => ['type' => 'text', 'label' => 'Sample text'],
                        'use_for'     => ['type' => 'textarea', 'label' => 'Use for', 'optional' => true],
                    ],
                ],
            ],
        ],

        /**
         * Two columns, each its own list.
         *
         * It used to be one list of rows, each tagged Do or Do not, which is the
         * right shape when the two genuinely correspond — this, not that. It is
         * the wrong shape for "we say / we do not say", where the two sides are
         * simply two lists of different lengths and nothing on the left is the
         * counterpart of anything on the right.
         *
         * The column headings are yours, so the block covers Do/Do not, We
         * say/We do not say, and Use/Avoid without three block types.
         */
        'do_dont' => [
            'label'  => 'Two columns of guidance',
            'hint'   => 'Do and do not, we say and we do not say. Two lists, added to separately.',
            'group'  => 'Guidance',
            'icon'   => 'dodont',
            'fields' => [
                'heading'    => ['type' => 'text', 'label' => 'Heading', 'optional' => true],
                'do_label'   => ['type' => 'text', 'label' => 'Left column heading', 'optional' => true],
                'dont_label' => ['type' => 'text', 'label' => 'Right column heading', 'optional' => true],
                'dos' => [
                    'type'   => 'repeater',
                    'label'  => 'Left column',
                    'item'   => 'Line',
                    'fields' => [
                        'text' => ['type' => 'text', 'label' => 'Line'],
                        'why'  => ['type' => 'textarea', 'label' => 'Why', 'optional' => true],
                    ],
                ],
                'donts' => [
                    'type'   => 'repeater',
                    'label'  => 'Right column',
                    'item'   => 'Line',
                    'fields' => [
                        'text' => ['type' => 'text', 'label' => 'Line'],
                        'why'  => ['type' => 'textarea', 'label' => 'Why', 'optional' => true],
                    ],
                ],
            ],
        ],

        'image' => [
            'label'  => 'Image',
            'hint'   => 'One image from the asset library, with a caption. Replacing the file updates this automatically.',
            'group'  => 'Images and media',
            'icon'   => 'image',
            'fields' => [
                'asset'   => ['type' => 'asset', 'label' => 'Image'],
                'alt'     => ['type' => 'text', 'label' => 'Alt text, describing the image for screen readers'],
                'caption' => ['type' => 'text', 'label' => 'Caption', 'optional' => true],
                'width'   => ['type' => 'select', 'label' => 'Width', 'options' => [
                    'text' => 'Text width',
                    'wide' => 'Wider than the text',
                    'half' => 'Half width',
                    // The image could not reach the full width other blocks can,
                    // because its own width capped it at 58rem whatever the
                    // block's Placement said. Full fills whatever the block is
                    // given, so Placement set to Full width now means it.
                    'full' => 'Fill the block',
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
            'group'  => 'Images and media',
            'icon'   => 'grid',
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
            'group'  => 'Guidance',
            'icon'   => 'compare',
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
            'group'  => 'Reference',
            'icon'   => 'download',
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
            'group'  => 'Text',
            'icon'   => 'callout',
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
            'group'  => 'Guidance',
            'icon'   => 'clearspace',
            'fields' => [
                'heading' => ['type' => 'text',   'label' => 'Heading', 'optional' => true],
                'asset'   => ['type' => 'asset',  'label' => 'Mark'],
                'measure' => ['type' => 'text',   'label' => 'Clear space equals', 'optional' => true,
                              'help' => 'The sentence under the diagram, for example "x is the height of the Y".'],
                'unit'    => ['type' => 'text',   'label' => 'Letter on the diagram', 'optional' => true,
                              'help' => 'Drawn in the gap on each side. Defaults to x.'],
                'ratio'   => ['type' => 'select', 'label' => 'Clear space around it', 'options' => [
                    '25' => 'A quarter of the mark', '50' => 'Half the mark',
                    '75' => 'Three quarters of the mark', '100' => 'The full mark',
                ], 'help' => 'Measured against the mark itself, not the page. Half the mark means the gap on each side is half the mark\'s width.'],
                'note'    => ['type' => 'textarea', 'label' => 'Note', 'optional' => true],
            ],
        ],

        'spacing_scale' => [
            'label'  => 'Spacing scale',
            'hint'   => 'Each step drawn at its real size, with its value.',
            'group'  => 'Reference',
            'icon'   => 'spacing',
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
            'group'  => 'Images and media',
            'icon'   => 'icons',
            'fields' => [
                'heading' => ['type' => 'text',   'label' => 'Heading', 'optional' => true],
                'tag'     => ['type' => 'text',   'label' => 'Asset tag',
                              'help' => 'The tag you gave the icons in Assets. Every asset carrying it appears here, so uploading another icon with that tag adds it to this grid on its own — you do not come back and edit the block.'],
                'note'    => ['type' => 'textarea', 'label' => 'Note', 'optional' => true],
            ],
        ],

        'contrast_pairs' => [
            'label'  => 'Contrast pairs',
            'hint'   => 'Colour combinations with their measured contrast ratio.',
            'group'  => 'Colour and type',
            'icon'   => 'contrast',
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
            'group'  => 'Guidance',
            'icon'   => 'misuse',
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
            'group'  => 'Reference',
            'icon'   => 'spec',
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
            'group'  => 'Reference',
            'icon'   => 'links',
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
            'group'  => 'Reference',
            'icon'   => 'contacts',
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
            'group'  => 'Text',
            'icon'   => 'checklist',
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
            'group'  => 'Images and media',
            'icon'   => 'video',
            'fields' => [
                'heading' => ['type' => 'text',  'label' => 'Heading', 'optional' => true],
                'url'     => ['type' => 'text',  'label' => 'YouTube or Vimeo address', 'optional' => true],
                // The only asset field that wants video rather than an image.
                'asset'   => ['type' => 'asset', 'label' => 'Or an uploaded MP4', 'accept' => 'video', 'optional' => true],
                'caption' => ['type' => 'text',  'label' => 'Caption', 'optional' => true],
            ],
        ],

        // -------------------------------------------------- presentation blocks

        'tabs' => [
            'label'  => 'Tabs',
            'hint'   => 'Variants of one thing in a single space.',
            'group'  => 'Layout',
            'icon'   => 'tabs',
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
            'group'  => 'Layout',
            'icon'   => 'accordion',
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
            'group'  => 'Text',
            'icon'   => 'code',
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
            'group'  => 'Text',
            'icon'   => 'steps',
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
            'group'  => 'Layout',
            'icon'   => 'hero',
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
            'group'  => 'Text',
            'icon'   => 'quote',
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
                'strip' => 'Continuous strip', 'stack' => 'Stacked bands',
            ]],
            'shape' => ['label' => 'Swatch shape', 'options' => [
                'inherit' => 'Site default', 'square' => 'Square', 'rounded' => 'Rounded', 'circle' => 'Circle',
            ]],
            'size' => ['label' => 'Swatch size', 'options' => [
                'small' => 'Small', 'medium' => 'Medium', 'large' => 'Large',
            ]],
            'columns' => ['label' => 'Columns, when a grid', 'options' => [
                'auto' => 'Fit the space', '2' => 'Two', '3' => 'Three',
                '4' => 'Four', '6' => 'Six', '11' => 'Eleven',
            ]],
            'ratio' => ['label' => 'Swatch shape ratio', 'options' => [
                'square' => 'Square', 'tall' => 'Tall', 'wide' => 'Wide', 'line' => 'Thin line',
            ]],
            'values' => ['label' => 'Show', 'options' => [
                'all' => 'Every value', 'hex' => 'Hex only', 'none' => 'Name only',
            ]],
            'labels' => ['label' => 'Label position', 'options' => [
                'below' => 'Below the swatch', 'inside' => 'Inside the swatch',
            ]],
        ],

        'colour_ramp' => [
            'layout' => ['label' => 'Layout', 'options' => [
                'strip' => 'Continuous strip', 'separate' => 'Separate swatches',
            ]],
            'height' => ['label' => 'Ramp height', 'options' => [
                'medium' => 'Medium', 'short' => 'Short', 'tall' => 'Tall',
            ]],
            'corners' => ['label' => 'Corners', 'options' => [
                'inherit' => 'Site default', 'square' => 'Square',
                'round' => 'Rounded', 'pill' => 'Pill',
            ]],
            'labels' => ['label' => 'Step labels', 'options' => [
                'inside' => 'Inside the ramp', 'below' => 'Below it', 'none' => 'None',
            ]],
            'values' => ['label' => 'Show the hex', 'options' => [
                'hover' => 'On hover', 'always' => 'Always', 'never' => 'Never',
            ]],
            'name' => ['label' => 'Family name', 'options' => [
                'above' => 'Above the ramp', 'beside' => 'Beside it',
            ]],
        ],

        'colour_story' => [
            'emphasis' => ['label' => 'Primary size', 'options' => [
                'half' => 'Half the width', 'two-thirds' => 'Two thirds', 'full' => 'Its own row',
            ]],
            'height' => ['label' => 'Height', 'options' => [
                'short' => 'Short', 'medium' => 'Medium', 'tall' => 'Tall',
            ]],
            'values' => ['label' => 'Show', 'options' => [
                'all' => 'Name, hex and use', 'hex' => 'Name and hex', 'name' => 'Name only',
            ]],
            'gap' => ['label' => 'Between panels', 'options' => [
                'none' => 'None, one surface', 'thin' => 'A thin line', 'wide' => 'Spaced apart',
            ]],
        ],

        // These three had no options at all, so they alone could not follow the
        // site's appearance or be overridden per block.
        'clearspace' => [
            'tone' => ['label' => 'Diagram colour', 'options' => [
                'accent' => 'Accent', 'ink' => 'Text colour', 'faint' => 'Faint',
            ]],
            'background' => ['label' => 'Behind the mark', 'options' => [
                'panel' => 'Panel', 'none' => 'None', 'dark' => 'Dark',
            ]],
        ],

        'video' => [
            'width' => ['label' => 'Width', 'options' => [
                'text' => 'Text column', 'wide' => 'Wider than the text',
            ]],
            'frame' => ['label' => 'Frame', 'options' => [
                'none' => 'None', 'panel' => 'Panel', 'shadow' => 'Shadow',
            ]],
        ],

        'accordion' => [
            'density' => ['label' => 'Density', 'options' => [
                'normal' => 'Normal', 'tight' => 'Tight',
            ]],
            'first' => ['label' => 'First entry', 'options' => [
                'closed' => 'Closed', 'open' => 'Open',
            ]],
        ],
        'type_specimen' => [
            'spec' => ['label' => 'Specification', 'options' => [
                'beside' => 'Beside the sample', 'under' => 'Under the sample', 'hidden' => 'Hidden',
            ]],
        ],
        'do_dont' => [
            'layout' => ['label' => 'Layout', 'options' => [
                'columns' => 'Side by side', 'rows' => 'Stacked',
            ]],
            'markers' => ['label' => 'Tick and cross', 'options' => [
                'show' => 'Show', 'hide' => 'Hide',
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
        'heading' => [
            // The level is what the heading means in the document, not how big
            // it looks — size is set separately, so a sub-heading can still be
            // large where that is the right emphasis.
            'level' => ['label' => 'Level', 'options' => [
                'h2' => 'Section (h2)', 'h3' => 'Sub-section (h3)', 'h4' => 'Minor (h4)',
            ]],
            'size' => ['label' => 'Size', 'options' => [
                'inherit' => 'Match the level', 'large' => 'Large', 'small' => 'Small',
            ]],
            'align' => ['label' => 'Alignment', 'options' => [
                'left' => 'Left', 'centre' => 'Centred',
            ]],
            'rule' => ['label' => 'Rule under it', 'options' => [
                'no' => 'No', 'yes' => 'Yes',
            ]],
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
            'custom' => 'A colour of your own',
        ]],
        // Only read when the background is set to custom. Kept as its own field
        // rather than overloading the select, so switching to a named background
        // and back does not lose the colour that was chosen.
        'background_hex' => ['type' => 'colour', 'label' => 'Background colour', 'optional' => true,
                                 'help' => 'Used when Background is set to a colour of your own. Text on it turns white automatically if the colour is dark.'],
        'spacing' => ['type' => 'select', 'label' => 'Space below', 'options' => [
            'normal' => 'Normal',
            'tight'  => 'Tight',
            'loose'  => 'Loose',
        ]],
        'divider' => ['type' => 'select', 'label' => 'Rule above', 'options' => [
            'no'  => 'No',
            'yes' => 'Yes',
        ]],
        /**
         * Corner radius and padding, per block, in pixels.
         *
         * Blank means follow the site's Appearance setting, which is what almost
         * every block should do — these exist for the one that genuinely needs to
         * differ, not as a per-block design system. Blank is therefore the
         * default, and an empty value is stored as empty rather than as the
         * global's current number, or moving the global would stop reaching any
         * block that had ever had this panel opened.
         *
         * Pixels because that is what was asked for and what a developer reads
         * off a spec. The global is in rem, and the two mix perfectly well inside
         * a calc().
         */
        'radius'  => ['type' => 'number', 'label' => 'Corner radius', 'unit' => 'px',
                      'optional' => true, 'min' => 0, 'max' => 96],
        'padding' => ['type' => 'number', 'label' => 'Padding', 'unit' => 'px',
                      'optional' => true, 'min' => 0, 'max' => 160],

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

/**
 * Cleans one set of values against one set of field definitions.
 *
 * Recursive, because a repeater can hold a repeater: a colour ramp is a list of
 * families, each holding its own list of steps. Flattening this into a single
 * level of nesting would have run a nested array through trim() and destroyed
 * it on the first save.
 *
 * $depth stops a definition that somehow refers to itself from recursing
 * forever. Three levels is far more than any block needs.
 */
function sanitise_fields(array $fields, array $values, int $depth = 0): array
{
    $clean = [];

    foreach ($fields as $key => $field) {
        $value = $values[$key] ?? null;

        if ($field['type'] === 'repeater') {
            $rows = [];
            if ($depth < 3) {
                foreach (is_array($value) ? $value : [] as $row) {
                    $rows[] = sanitise_fields($field['fields'], is_array($row) ? $row : [], $depth + 1);
                }
            }
            $clean[$key] = $rows;
        } elseif ($field['type'] === 'select') {
            // Cast to string: PHP turns numeric option keys into integers, and
            // stored JSON should hold one consistent type.
            $flat = is_scalar($value) ? (string) $value : '';
            $clean[$key] = (string) (isset($field['options'][$flat]) ? $flat : array_key_first($field['options']));
        } elseif ($field['type'] === 'asset') {
            // Store the id only. A missing or bogus id renders as nothing.
            $clean[$key] = (string) (int) (is_scalar($value) ? $value : 0);
        } elseif ($field['type'] === 'colour') {
            $clean[$key] = sanitise_colour(is_scalar($value) ? (string) $value : '');
        } else {
            // Anything that is not a scalar cannot be text, so it becomes empty
            // rather than "Array".
            $clean[$key] = is_scalar($value) ? trim((string) $value) : '';
        }
    }

    return $clean;
}

/**
 * Brings stored blocks up to the current shape.
 *
 * `sanitise_blocks()` strips anything the definitions do not name, which is what
 * makes stored JSON trustworthy — and it is also why a field that is renamed or
 * restructured has to be migrated *before* the editor ever sees it. Open a page,
 * save it, and whatever was not migrated is gone.
 *
 * So this runs on every path that reads blocks: rendering, the editor, and the
 * preview. It is idempotent, it never runs on save, and a block already in the
 * new shape is returned untouched.
 *
 * There is no migration file for this. The blocks are JSON on a row, which is
 * what makes a new block type need no migration; the price is that a change of
 * shape is code rather than SQL, and has to be written here.
 */
function block_upgrade(array $blocks): array
{
    foreach ($blocks as $i => $block) {
        if (($block['type'] ?? '') !== 'do_dont' || empty($block['data']['pairs'])) {
            continue;
        }

        // Paired rows became two independent lists. Each row's stance decides
        // which column it lands in, and the order within a column is the order
        // the rows were in, so a page reads the same afterwards.
        $dos = $blocks[$i]['data']['dos'] ?? [];
        $donts = $blocks[$i]['data']['donts'] ?? [];

        foreach ((array) $block['data']['pairs'] as $pair) {
            $line = ['text' => (string) ($pair['rule'] ?? ''), 'why' => (string) ($pair['why'] ?? '')];
            if ($line['text'] === '' && $line['why'] === '') {
                continue;
            }
            if (($pair['stance'] ?? 'do') === 'dont') {
                $donts[] = $line;
            } else {
                $dos[] = $line;
            }
        }

        $blocks[$i]['data']['dos'] = $dos;
        $blocks[$i]['data']['donts'] = $donts;
        unset($blocks[$i]['data']['pairs']);
    }

    return $blocks;
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
        $data = sanitise_fields($types[$type]['fields'], is_array($block['data'] ?? null) ? $block['data'] : []);

        $settings = [];
        foreach (block_settings_fields() as $key => $field) {
            $value = $block['settings'][$key] ?? null;
            $settings[$key] = match ($field['type']) {
                'select' => (string) (isset($field['options'][$value]) ? $value : array_key_first($field['options'])),
                'colour' => sanitise_colour((string) $value),
                // Blank stays blank: it means "follow the site setting", and
                // coercing it to 0 would pin a block at no radius for ever.
                // Anything that is not a number is treated as blank rather than
                // cast, because (int) "abc" is 0 — a real value meaning square
                // corners — and silently choosing that for someone is worse than
                // ignoring what they typed.
                'number' => is_numeric(trim((string) $value))
                    ? (string) max(
                        (int) ($field['min'] ?? 0),
                        min((int) ($field['max'] ?? 999), (int) $value)
                    )
                    : '',
                // Anchors end up in a URL, so only safe characters survive.
                default  => strtolower(preg_replace('/[^A-Za-z0-9\-]/', '', (string) $value) ?? ''),
            };
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
 *
 * $indexFrom offsets those indexes. The preview lifts an opening hero out of
 * the list before calling this, and without the offset every remaining block
 * would report an index one lower than the one the editor holds, so every
 * click in the canvas would select the wrong block.
 */
function render_blocks(array $blocks, bool $editable = false, int $indexFrom = 0): void
{
    block_editing($editable);
    // Old shapes brought forward before anything is drawn, so a page written
    // against a previous definition still renders and still reads the same.
    $blocks = block_upgrade($blocks);

    foreach ($blocks as $offset => $block) {
        $index = $indexFrom + (int) $offset;
        $file = APP_DIR . '/blocks/' . basename((string) $block['type']) . '.php';
        if (!is_file($file)) {
            continue;
        }
        $data     = $block['data'] ?? [];
        $options  = array_merge(block_options_defaults((string) $block['type']), $block['options'] ?? []);
        $settings = array_merge(block_settings_defaults(), $block['settings'] ?? []);
        // Only an opener renders as a full-bleed heading; see hero.php.
        $isOpener = false;

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

        /**
         * Per-block radius, padding and background colour, as custom properties.
         *
         * Properties rather than direct declarations so the stylesheet keeps
         * deciding *what* they apply to — the fill, the nested inner radius, the
         * outline treatment — and this only supplies the numbers. A blank value
         * emits nothing at all, so the stylesheet's own fallback to the global
         * Appearance setting is what runs.
         */
        $style = '';
        $custom = '';
        if (!$isHero) {
            if (($settings['radius'] ?? '') !== '') {
                $style .= '--zone-radius:' . (int) $settings['radius'] . 'px;';
            }
            if (($settings['padding'] ?? '') !== '') {
                $style .= '--zone-pad:' . (int) $settings['padding'] . 'px;';
            }
            if (($settings['background'] ?? '') === 'custom') {
                $custom = sanitise_colour((string) ($settings['background_hex'] ?? ''));
                if ($custom !== '') {
                    $style .= '--zone-bg:' . $custom . ';';
                }
            }
        }

        // Dark text on a dark colour someone just chose is the obvious way for
        // this to go wrong, so the choice is made here rather than left to them.
        // The same measurement the contrast block uses.
        if ($custom !== '' && contrast_ratio($custom, '#FFFFFF') > contrast_ratio($custom, '#1F1F1F')) {
            $classes[] = 'zone--on-dark';
        }

        printf(
            '<div class="%s"%s%s%s>',
            e(implode(' ', $classes)),
            $style !== '' ? ' style="' . e($style) . '"' : '',
            $settings['anchor'] !== '' ? ' id="' . e($settings['anchor']) . '"' : '',
            $editable ? ' data-block="' . (int) $index . '"' : ''
        );

        if ($editable) {
            ob_start();
            require $file;
            echo block_or_placeholder(ob_get_clean(), (string) $block['type']);
        } else {
            require $file;
        }
        echo '</div>';
    }

    // Left off again, so a later render on the same request — the section view
    // draws a page after the preview has drawn one — is not silently editable.
    block_editing(false);
}

/**
 * Whether blocks are being rendered for the editor's canvas.
 *
 * Held here rather than passed down, because editable() is called from inside a
 * renderer that has no idea why it is being rendered, and threading a flag
 * through twenty-seven of them to say so would be worse.
 */
function block_editing(?bool $set = null): bool
{
    static $on = false;
    if ($set !== null) {
        $on = $set;
    }
    return $on;
}

/**
 * Marks a piece of text on the canvas as editable in place, and says which
 * field it is.
 *
 * $path is the route to the value inside the block's data: 'heading' for a
 * plain field, 'pairs.0.rule' for one inside a repeater. The editor resolves it
 * against the block it belongs to.
 *
 * $multiline says the value holds paragraphs rather than a single line, so the
 * canvas serialises it back with the blank lines between them intact.
 *
 * Emits nothing at all on a published page: there is no editing there, and no
 * reason to ship the attributes.
 */
function editable(string $path, bool $multiline = false): string
{
    if (!block_editing()) {
        return '';
    }
    return ' data-field="' . e($path) . '"' . ($multiline ? ' data-multiline="1"' : '');
}

/**
 * A block's output, or a stand-in when it would show nothing.
 *
 * A block with nothing in it yet renders nothing at all, which on the editor
 * canvas means a newly added block is invisible and has no height to click.
 * Only ever called in edit mode, so the published page is untouched, and called
 * from one place rather than added to 25 renderers, so it cannot drift.
 */
function block_or_placeholder(string $html, string $type): string
{
    return block_shows_something($html) ? $html : block_placeholder($type);
}

/**
 * Whether rendered block output would actually be visible to a reader.
 *
 * Text is the usual test, but a block can be perfectly full and still hold no
 * text at all: an image, an icon grid, a video. Those count too.
 */
function block_shows_something(string $html): bool
{
    if (trim(strip_tags($html)) !== '') {
        return true;
    }
    return (bool) preg_match('/<(img|svg|iframe|video|audio|input)\b/i', $html);
}

/** The stand-in shown on the editor canvas for a block with nothing in it. */
function block_placeholder(string $type): string
{
    $def = block_types()[$type] ?? null;
    if (!$def) {
        return '';
    }
    return '<p class="block__placeholder"><strong>' . e($def['label']) . '</strong>'
         . ($def['hint'] ? '<span>' . e($def['hint']) . '</span>' : '')
         . '<span class="block__placeholder-cue">Nothing in it yet. Fill it in on the right.</span></p>';
}

/** Turn plain text with blank lines into paragraphs. No HTML passes through. */
function paragraphs(string $text): string
{
    $text = trim($text);
    // No text means no paragraph. Returning "<p></p>" instead left an empty
    // paragraph taking up space on the page wherever a body was not filled in.
    if ($text === '') {
        return '';
    }
    $parts = preg_split("/\n\s*\n/", $text) ?: [];
    $out = '';
    foreach ($parts as $part) {
        $part = trim($part);
        if ($part === '') {
            continue;
        }
        $list = paragraph_list($part);
        $out .= $list ?? '<p>' . rich_inline(nl2br(e($part))) . '</p>';
    }
    return $out;
}

/**
 * A block of lines that are all list items, as a list. Null if they are not.
 *
 * `- ` or `* ` makes a bullet, `1.` makes a number. All or nothing within a
 * paragraph: a block where only some lines start with a dash is prose that
 * happens to contain a dash, and turning half of it into a list would be worse
 * than leaving it alone.
 *
 * `*` is accepted as a bullet only at the start of a line followed by a space,
 * which is what keeps it from colliding with `*italic*` — an italic run has no
 * space after its opening marker.
 */
function paragraph_list(string $part): ?string
{
    $lines = preg_split('/\n/', $part) ?: [];
    $lines = array_values(array_filter(array_map('trim', $lines), fn($l) => $l !== ''));
    if (count($lines) < 1) {
        return null;
    }

    $ordered = null;
    $items = [];

    foreach ($lines as $line) {
        if (preg_match('/^[-*]\s+(.*)$/', $line, $m)) {
            $isOrdered = false;
        } elseif (preg_match('/^\d+[.)]\s+(.*)$/', $line, $m)) {
            $isOrdered = true;
        } else {
            return null;                       // not every line, so not a list
        }
        if ($ordered === null) {
            $ordered = $isOrdered;
        } elseif ($ordered !== $isOrdered) {
            return null;                       // mixed, so left as written
        }
        $items[] = rich_inline(e(trim($m[1])));
    }

    $tag = $ordered ? 'ol' : 'ul';
    return '<' . $tag . ' class="prose__list">'
         . implode('', array_map(fn($i) => '<li>' . $i . '</li>', $items))
         . '</' . $tag . '>';
}

/**
 * Bold, italic, code and links, from markers in plain text.
 *
 * **THIS RUNS ON ALREADY-ESCAPED TEXT AND MUST CONTINUE TO.** That is the whole
 * safety argument, and it is why the project's rule — blocks never render raw
 * HTML — is still exactly true with formatting in place. What is stored is plain
 * text: `**bold**`, never `<strong>`. By the time this sees it, `e()` has turned
 * every `<` into `&lt;`, so nothing anyone typed can become a tag. The only tags
 * in the output are the four written here. A malformed marker renders as literal
 * asterisks, which is visible and harmless, rather than as broken markup.
 *
 * It also means a pasted Word document arrives as text and stays text. There is
 * no sanitiser to keep in step with a browser's idea of what it may produce.
 *
 * Underscores are deliberately not italic markers. Token names are full of them
 * — `--s-radius`, `figma_file_key` — and a brand hub is a document where those
 * get written down constantly.
 */
function rich_inline(string $escaped): string
{
    // Bold before italic: the single-asterisk pattern would otherwise eat the
    // first two of a `**`. The tags it writes contain no asterisks, so the
    // second pass cannot reach inside them.
    $out = preg_replace('/\*\*(?=\S)(.+?)(?<=\S)\*\*/s', '<strong>$1</strong>', $escaped) ?? $escaped;
    $out = preg_replace('/(?<![\w*])\*(?=\S)([^*]+?)(?<=\S)\*(?![\w*])/s', '<em>$1</em>', $out) ?? $out;
    $out = preg_replace('/`(?=\S)([^`]+?)(?<=\S)`/s', '<code>$1</code>', $out) ?? $out;

    // Only http and https become links, as everywhere else in this project. A
    // link to anything else renders as the literal text someone typed.
    $out = preg_replace_callback(
        '/\[(?=\S)([^\]]+?)\]\((https?:&#0*58;|https?:\/\/)([^\s)]*)\)/i',
        function (array $m): string {
            // e() leaves a colon alone, so the scheme arrives intact; the
            // alternative above is only there in case an entity form appears.
            $url = str_starts_with(strtolower($m[2]), 'http') && str_contains($m[2], '//')
                ? $m[2] . $m[3]
                : '';
            return $url === ''
                ? '[' . $m[1] . '](' . $m[2] . $m[3] . ')'
                : '<a href="' . $url . '">' . $m[1] . '</a>';
        },
        $out
    ) ?? $out;

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
function render_bare_block(array $block, bool $editable = false): void
{
    $type = (string) ($block['type'] ?? '');
    $file = APP_DIR . '/blocks/' . $type . '.php';
    if (!isset(block_types()[$type]) || !is_file($file)) {
        return;
    }

    $data    = $block['data'] ?? [];
    $options = array_merge(block_options_defaults($type), $block['options'] ?? []);
    // This block opens the document, so its headline is the page's heading.
    $isOpener = true;

    if ($editable) {
        // An opener with no headline yet is as invisible as any other empty
        // block, and it is the one block a writer starts with.
        block_editing(true);
        ob_start();
        require $file;
        echo block_or_placeholder(ob_get_clean(), $type);
        block_editing(false);
        return;
    }
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
