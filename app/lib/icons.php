<?php
declare(strict_types=1);

/**
 * A small, deliberately limited icon set. Inline SVG rather than an icon font
 * or a library, so there is nothing to load, they inherit colour and size from
 * their context, and they cannot go missing on a slow connection.
 *
 * All drawn on a 24 unit grid with a 1.5 stroke, so they sit together evenly.
 */
function icon_set(): array
{
    return [
        'none'      => ['label' => 'No icon', 'path' => ''],
        'layers'    => ['label' => 'Layers',      'path' => '<path d="M12 3 3 8l9 5 9-5-9-5Z"/><path d="m3 13 9 5 9-5"/>'],
        'palette'   => ['label' => 'Palette',     'path' => '<path d="M12 3a9 9 0 1 0 0 18c.9 0 1.5-.7 1.5-1.5 0-.4-.2-.8-.4-1-.3-.3-.4-.6-.4-1 0-.8.6-1.5 1.5-1.5H16a5 5 0 0 0 5-5c0-4.4-4-8-9-8Z"/><circle cx="7.5" cy="11.5" r="1"/><circle cx="12" cy="7.5" r="1"/><circle cx="16.5" cy="11.5" r="1"/>'],
        'type'      => ['label' => 'Typography',  'path' => '<path d="M5 6V4h14v2"/><path d="M12 4v16"/><path d="M9 20h6"/>'],
        'speech'    => ['label' => 'Speech',      'path' => '<path d="M21 12a8 8 0 0 1-8 8H4l2.3-2.3A8 8 0 1 1 21 12Z"/>'],
        'image'     => ['label' => 'Image',       'path' => '<rect x="3" y="4" width="18" height="16" rx="2"/><circle cx="8.5" cy="9.5" r="1.5"/><path d="m3 17 5-4 4 3 3-2 6 5"/>'],
        'grid'      => ['label' => 'Grid',        'path' => '<rect x="3" y="3" width="7" height="7" rx="1"/><rect x="14" y="3" width="7" height="7" rx="1"/><rect x="3" y="14" width="7" height="7" rx="1"/><rect x="14" y="14" width="7" height="7" rx="1"/>'],
        'box'       => ['label' => 'Packaging',   'path' => '<path d="M21 8 12 3 3 8v8l9 5 9-5V8Z"/><path d="m3 8 9 5 9-5"/><path d="M12 13v8"/>'],
        'download'  => ['label' => 'Download',    'path' => '<path d="M12 3v12"/><path d="m7 11 5 5 5-5"/><path d="M4 20h16"/>'],
        'book'      => ['label' => 'Book',        'path' => '<path d="M4 4.5A1.5 1.5 0 0 1 5.5 3H20v18H5.5A1.5 1.5 0 0 1 4 19.5Z"/><path d="M4 16.5h16"/>'],
        'star'      => ['label' => 'Star',        'path' => '<path d="m12 3 2.6 5.5 6 .9-4.3 4.3 1 6.1-5.3-2.9L6.7 19.8l1-6.1L3.4 9.4l6-.9L12 3Z"/>'],
        'compass'   => ['label' => 'Compass',     'path' => '<circle cx="12" cy="12" r="9"/><path d="m15.5 8.5-2 5-5 2 2-5 5-2Z"/>'],
        'tag'       => ['label' => 'Tag',         'path' => '<path d="M3 3h8l10 10-8 8L3 11V3Z"/><circle cx="7.5" cy="7.5" r="1.25"/>'],
        'shield'    => ['label' => 'Shield',      'path' => '<path d="M12 3 4 6v6c0 4.5 3.4 8.3 8 9 4.6-.7 8-4.5 8-9V6l-8-3Z"/>'],
        'megaphone' => ['label' => 'Megaphone',   'path' => '<path d="M4 10v4a1 1 0 0 0 1 1h3l6 4V5L8 9H5a1 1 0 0 0-1 1Z"/><path d="M18 9a4 4 0 0 1 0 6"/>'],
    ];
}

/** Renders one icon. Returns an empty string for an unknown or absent name. */
function icon(string $name, string $class = 'icon'): string
{
    $set = icon_set();
    if (!isset($set[$name]) || $set[$name]['path'] === '') {
        return '';
    }
    return '<svg class="' . e($class) . '" viewBox="0 0 24 24" width="20" height="20" fill="none"'
         . ' stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round"'
         . ' aria-hidden="true" focusable="false">' . $set[$name]['path'] . '</svg>';
}

/** The section-to-icon map, stored as JSON in settings. */
function section_icons(): array
{
    $raw = (string) setting('section_icons', '');
    $map = json_decode($raw, true);
    return is_array($map) ? $map : [];
}

function section_icon(string $section): string
{
    $map = section_icons();
    if (isset($map[$section])) {
        return (string) $map[$section];
    }
    return section_icon_guess($section);
}

/**
 * A sensible icon for a section nobody has chosen one for, inferred from its
 * name. Better than every card carrying the same generic mark.
 */
function section_icon_guess(string $section): string
{
    $name = strtolower($section);
    $rules = [
        'foundation' => 'layers',
        'basic'      => 'layers',
        'start'      => 'compass',
        'voice'      => 'speech',
        'tone'       => 'speech',
        'word'       => 'speech',
        'writing'    => 'speech',
        'colour'     => 'palette',
        'color'      => 'palette',
        'type'       => 'type',
        'applicat'   => 'box',
        'packag'     => 'box',
        'channel'    => 'megaphone',
        'marketing'  => 'megaphone',
        'social'     => 'megaphone',
        'standard'   => 'shield',
        'legal'      => 'shield',
        'guideline'  => 'book',
        'resource'   => 'download',
        'download'   => 'download',
        'asset'      => 'download',
        'photo'      => 'image',
        'image'      => 'image',
        'product'    => 'tag',
    ];
    foreach ($rules as $needle => $iconName) {
        if (str_contains($name, $needle)) {
            return $iconName;
        }
    }
    return 'compass';
}

function section_icon_save(string $section, string $iconName): void
{
    $map = section_icons();
    // 'none' is stored rather than removed, so choosing no icon is a real
    // choice and not the same as never having chosen one.
    $map[$section] = isset(icon_set()[$iconName]) ? $iconName : 'none';
    setting_save('section_icons', json_encode($map));
}

/** Keeps a section's icon with it when the section is renamed. */
function section_icon_rename(string $from, string $to): void
{
    $map = section_icons();
    if (isset($map[$from])) {
        $map[$to] = $map[$from];
        if ($from !== $to) {
            unset($map[$from]);
        }
        setting_save('section_icons', json_encode($map));
    }
}

/**
 * Interface icons, kept separate from the section icon set so the section
 * picker only ever offers icons that make sense for a section.
 */
function ui_icon_set(): array
{
    return [
        'pages'    => '<path d="M4 4.5A1.5 1.5 0 0 1 5.5 3H15l5 5v13H5.5A1.5 1.5 0 0 1 4 19.5Z"/><path d="M14 3v6h6"/>',
        'assets'   => '<rect x="3" y="4" width="18" height="16" rx="2"/><circle cx="8.5" cy="9.5" r="1.5"/><path d="m3 17 5-4 4 3 3-2 6 5"/>',
        'people'   => '<circle cx="9" cy="8" r="3.25"/><path d="M3 20a6 6 0 0 1 12 0"/><path d="M16.5 5.5a3 3 0 0 1 0 5.4"/><path d="M18 20a6 6 0 0 0-2.5-4.9"/>',
        'settings' => '<circle cx="12" cy="12" r="3"/><path d="M19.4 14.5a1.6 1.6 0 0 0 .3 1.8l.1.1a2 2 0 1 1-2.8 2.8l-.1-.1a1.6 1.6 0 0 0-1.8-.3 1.6 1.6 0 0 0-1 1.5V21a2 2 0 1 1-4 0v-.2a1.6 1.6 0 0 0-1-1.5 1.6 1.6 0 0 0-1.8.3l-.1.1a2 2 0 1 1-2.8-2.8l.1-.1a1.6 1.6 0 0 0 .3-1.8 1.6 1.6 0 0 0-1.5-1H3a2 2 0 1 1 0-4h.2a1.6 1.6 0 0 0 1.5-1 1.6 1.6 0 0 0-.3-1.8l-.1-.1a2 2 0 1 1 2.8-2.8l.1.1a1.6 1.6 0 0 0 1.8.3H9a1.6 1.6 0 0 0 1-1.5V3a2 2 0 1 1 4 0v.2a1.6 1.6 0 0 0 1 1.5 1.6 1.6 0 0 0 1.8-.3l.1-.1a2 2 0 1 1 2.8 2.8l-.1.1a1.6 1.6 0 0 0-.3 1.8V9a1.6 1.6 0 0 0 1.5 1H21a2 2 0 1 1 0 4h-.2a1.6 1.6 0 0 0-1.4 1Z"/>',
        'view'     => '<path d="M14 4h6v6"/><path d="M20 4 11 13"/><path d="M18 14v5a1 1 0 0 1-1 1H5a1 1 0 0 1-1-1V7a1 1 0 0 1 1-1h5"/>',
        'signout'  => '<path d="M9 21H5a1 1 0 0 1-1-1V4a1 1 0 0 1 1-1h4"/><path d="m16 17 5-5-5-5"/><path d="M21 12H9"/>',
        'menu'     => '<path d="M4 6h16"/><path d="M4 12h16"/><path d="M4 18h16"/>',
        'close'    => '<path d="M6 6l12 12"/><path d="M18 6 6 18"/>',
        'back'     => '<path d="M19 12H5"/><path d="m11 18-6-6 6-6"/>',
        'history'  => '<path d="M3 12a9 9 0 1 0 3-6.7L3 8"/><path d="M3 4v4h4"/><path d="M12 8v4l3 2"/>',
        'add'      => '<path d="M12 5v14"/><path d="M5 12h14"/>',
    ];
}

function ui_icon(string $name, string $class = 'icon'): string
{
    $set = ui_icon_set();
    if (!isset($set[$name])) {
        return '';
    }
    return '<svg class="' . e($class) . '" viewBox="0 0 24 24" width="24" height="24" fill="none"'
         . ' stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round"'
         . ' aria-hidden="true" focusable="false">' . $set[$name] . '</svg>';
}
