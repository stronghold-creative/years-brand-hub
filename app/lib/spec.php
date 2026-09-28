<?php
declare(strict_types=1);

/**
 * The derived spec: what a component's own CSS says about itself.
 *
 * A developer opening a component wants the numbers — the radius, which colour,
 * how much padding, what type. Those already exist in the CSS; typing them into
 * a table beside it only creates a second copy to keep in step, and the copy is
 * the one that goes stale. So they are read out of the CSS instead, and matched
 * against the site's own tokens, which makes the table an audit as well as a
 * description: a value that is not one of the brand's is either a deliberate
 * exception worth a note or a mistake worth catching.
 *
 * IT IS A TEXT PASS, NOT A PARSER, AND THE PAGE SAYS SO. It reads declarations.
 * It does not resolve the cascade, follow `var()` to a value, expand shorthand
 * beyond the obvious, or know which rule actually wins. A spec table that claimed
 * to would be wrong in ways nobody could see; one that is modest is useful.
 *
 * See section 5 of docs/COMPONENTS.md.
 */

/** The declarations worth reporting, grouped as the table shows them. */
function spec_groups(): array
{
    return [
        'shape'   => ['label' => 'Shape',   'props' => ['border-radius', 'border-top-left-radius',
                       'border-top-right-radius', 'border-bottom-right-radius', 'border-bottom-left-radius',
                       'border-width', 'border-style', 'border', 'outline', 'outline-width', 'outline-offset']],
        'colour'  => ['label' => 'Colour',  'props' => ['color', 'background', 'background-color',
                       'border-color', 'outline-color', 'fill', 'stroke', 'box-shadow', 'accent-color']],
        'space'   => ['label' => 'Space',   'props' => ['padding', 'padding-top', 'padding-right',
                       'padding-bottom', 'padding-left', 'margin', 'margin-top', 'margin-right',
                       'margin-bottom', 'margin-left', 'gap', 'row-gap', 'column-gap',
                       'min-height', 'min-width', 'width', 'height']],
        'type'    => ['label' => 'Type',    'props' => ['font-family', 'font-size', 'font-weight',
                       'line-height', 'letter-spacing', 'text-transform', 'font-style']],
    ];
}

/**
 * Every token a value could legitimately be, as `--name => ['value', 'kind']`.
 *
 * Two sources, both already the single answer to their own question: the
 * appearance settings, and the colours the guidelines publish. Nothing is
 * hardcoded here, so a colour added to the palette is a known token on the next
 * page load without anyone touching this file.
 */
function spec_tokens(): array
{
    $out = [];

    foreach (style_flat() as $key => $field) {
        if (empty($field['css'])) {
            continue;
        }
        $out[$field['css']] = [
            'value' => style_css_value($key),
            'kind'  => ($field['type'] ?? '') === 'colour' ? 'colour' : 'length',
            'label' => $field['label'] ?? $key,
        ];
    }

    foreach (design_colour_tokens() as $name => $hex) {
        $out[$name] = ['value' => $hex, 'kind' => 'colour', 'label' => ltrim($name, '-')];
    }

    return $out;
}

/**
 * A colour written the same way whichever way it was typed, so #FFF, #ffffff and
 * #FFFFFFFF all compare equal. Anything that is not a hex colour comes back as
 * lowercased text, which is right for `rgb(...)` — two spellings of the same
 * rgb() will not match, and saying "not a token" there is honest rather than
 * clever.
 */
function spec_normalise_colour(string $value): string
{
    $value = strtolower(trim($value));
    if (!preg_match('/^#([0-9a-f]{3,8})$/', $value, $m)) {
        return $value;
    }
    $hex = $m[1];
    if (strlen($hex) === 3 || strlen($hex) === 4) {
        $expanded = '';
        foreach (str_split(substr($hex, 0, 3)) as $char) {
            $expanded .= $char . $char;
        }
        $hex = $expanded;
    }
    // An alpha channel is dropped for matching: #7257B7FF is the accent.
    return '#' . substr($hex, 0, 6);
}

/** A length written the same way, so `0`, `0px` and `0rem` all compare equal. */
function spec_normalise_length(string $value): string
{
    $value = strtolower(trim($value));
    if (!preg_match('/^(-?[0-9]*\.?[0-9]+)([a-z%]*)$/', $value, $m)) {
        return $value;
    }
    $number = rtrim(rtrim(number_format((float) $m[1], 4, '.', ''), '0'), '.');
    if ($number === '' || $number === '-') {
        $number = '0';
    }
    return $number === '0' ? '0' : $number . $m[2];
}

/**
 * The token a literal value is, if it is one.
 *
 * Returns the token name, or null. Compared like for like: 6px is not matched
 * against a token held in rem, because converting would need to assume a root
 * font size and the body size is itself a setting someone can move.
 */
function spec_match(string $value, string $kind, array $tokens): ?string
{
    $wanted = $kind === 'colour' ? spec_normalise_colour($value) : spec_normalise_length($value);
    if ($wanted === '') {
        return null;
    }
    foreach ($tokens as $name => $token) {
        if ($token['kind'] !== $kind) {
            continue;
        }
        $have = $kind === 'colour'
            ? spec_normalise_colour((string) $token['value'])
            : spec_normalise_length((string) $token['value']);
        if ($have !== '' && $have === $wanted) {
            return $name;
        }
    }
    return null;
}

/**
 * Splits CSS into selector and declaration pairs.
 *
 * Comments go first, then the text is cut on `}`. Each piece is everything up to
 * its last `{` as the selector and the rest as declarations, which handles a rule
 * inside a media query without needing to understand at-rules: the prelude ends
 * up attached to the selector, where it reads as useful context rather than as a
 * mistake.
 */
function spec_rules(string $css): array
{
    $css = preg_replace('~/\*.*?\*/~s', '', $css) ?? '';
    $rules = [];

    foreach (explode('}', $css) as $chunk) {
        $at = strrpos($chunk, '{');
        if ($at === false) {
            continue;
        }
        $selector = trim(substr($chunk, 0, $at));
        $body     = trim(substr($chunk, $at + 1));
        if ($selector === '' || $body === '') {
            continue;
        }

        // A rule inside an at-rule arrives with the prelude still attached. The
        // selector is tidied down to the rule itself, but the prelude is kept
        // separately rather than thrown away — it is where "responds to width"
        // and "respects reduced motion" live, and the first version of this
        // dropped it and then reported both as absent.
        $at_rule = '';
        if (($inner = strrpos($selector, '{')) !== false) {
            $at_rule  = trim(substr($selector, 0, $inner));
            $selector = trim(substr($selector, $inner + 1));
        }
        $rules[] = ['selector' => $selector, 'at' => $at_rule, 'body' => $body];
    }
    return $rules;
}

/** The states a component's CSS actually defines, which is as telling as the values. */
function spec_states(array $rules): array
{
    $looking = [
        ':hover'         => 'Hover',
        ':focus-visible' => 'Keyboard focus',
        ':focus'         => 'Focus',
        ':active'        => 'Pressed',
        ':disabled'      => 'Disabled',
        '[aria-'         => 'ARIA state',
        '[data-'         => 'Data state',
        '@media'         => 'Responds to width',
        'prefers-reduced-motion' => 'Respects reduced motion',
    ];
    $found = [];
    foreach ($rules as $rule) {
        // The selector and the at-rule it sits in, since @media and
        // prefers-reduced-motion are only ever in the second.
        $text = $rule['selector'] . ' ' . ($rule['at'] ?? '');
        foreach ($looking as $needle => $label) {
            if (str_contains($text, $needle)) {
                $found[$label] = true;
            }
        }
    }
    return array_keys($found);
}

/**
 * Reads a component's CSS and returns the spec, grouped.
 *
 * Each row carries a stable key, which is what a written note is filed under. The
 * key includes the value on purpose: a note explaining why a radius is 6px should
 * not silently follow that radius to 28px. Changing the value detaches the note,
 * which is the right kind of losing it — visible, and at the moment the thing it
 * described stopped being true.
 */
function component_spec(string $css, array $overrides = []): array
{
    $css = trim($css);
    if ($css === '') {
        return ['groups' => [], 'states' => [], 'flagged' => 0];
    }

    $tokens = spec_tokens();
    $rules  = spec_rules($css);
    $groups = [];
    $flagged = 0;
    $seen = [];

    foreach ($rules as $rule) {
        foreach (explode(';', $rule['body']) as $declaration) {
            $colon = strpos($declaration, ':');
            if ($colon === false) {
                continue;
            }
            $property = strtolower(trim(substr($declaration, 0, $colon)));
            $value    = trim(substr($declaration, $colon + 1));
            if ($property === '' || $value === '' || str_starts_with($property, '--')) {
                continue;
            }

            foreach (spec_groups() as $key => $group) {
                if (!in_array($property, $group['props'], true)) {
                    continue;
                }

                // A value that IS a token is on-system by definition, and there is
                // nothing to match — it is the answer.
                //
                // A token inside a larger value is a different thing and must not
                // be reported as the same one: `border: 1px solid var(--rule)` has
                // a hardcoded width in it, and calling that declaration on-system
                // because it mentions a token would hide exactly what this table
                // is for. It is reported as partly tokenised, and not flagged,
                // because shorthand is not taken apart here.
                if (preg_match('/^var\(\s*(--[a-z0-9-]+)\s*(?:,[^)]*)?\)$/i', trim($value), $m)) {
                    $row = ['token' => $m[1], 'partial' => false, 'flagged' => false];
                } elseif (preg_match_all('/var\(\s*(--[a-z0-9-]+)/i', $value, $m)) {
                    $row = ['token' => implode(', ', $m[1]), 'partial' => true, 'flagged' => false];
                } else {
                    $kind  = $key === 'colour' ? 'colour' : 'length';
                    $token = spec_match($value, $kind, $tokens);
                    // Only single values are judged. "1px solid red" or "0 auto"
                    // is a shorthand this does not take apart, and calling it
                    // off-system would be reporting its own limits as a finding.
                    $simple = preg_match('/^[#a-z0-9.%-]+$/i', $value) === 1;
                    $row = [
                        'token'   => $token,
                        'partial' => false,
                        'flagged' => $token === null && $simple && $value !== 'inherit'
                                     && $value !== 'none' && $value !== 'auto' && $value !== '0',
                    ];
                }

                $id = substr(md5($key . '|' . $property . '|' . $value), 0, 12);
                if (isset($seen[$id])) {
                    // The same declaration in two rules is one fact, but both
                    // selectors are worth showing.
                    $groups[$key]['rows'][$seen[$id]]['where'][] = $rule['selector'];
                    continue 2;
                }

                $groups[$key]['label'] = $group['label'];
                $groups[$key]['rows'][] = $row + [
                    'id'       => $id,
                    'property' => $property,
                    'value'    => $value,
                    'where'    => [$rule['selector']],
                    'note'     => $overrides[$id] ?? null,
                ];
                $seen[$id] = count($groups[$key]['rows']) - 1;
                if ($row['flagged']) {
                    $flagged++;
                }
                continue 2;
            }
        }
    }

    // In the order spec_groups() declares them, not the order they were found,
    // so two components read the same way.
    $ordered = [];
    foreach (spec_groups() as $key => $group) {
        if (!empty($groups[$key]['rows'])) {
            $ordered[$key] = $groups[$key];
        }
    }

    return ['groups' => $ordered, 'states' => spec_states($rules), 'flagged' => $flagged];
}

/** A component's written notes, keyed by spec row. */
function component_notes(array $component): array
{
    $notes = json_decode((string) ($component['notes_override'] ?? '{}'), true);
    return is_array($notes) ? $notes : [];
}

/**
 * Saves the written notes.
 *
 * A blank note removes the row rather than storing an empty string, so the column
 * holds only what someone actually wrote and a cleared note leaves the derived
 * line to speak for itself.
 */
function component_notes_save(int $id, array $notes, ?int $userId): void
{
    $clean = [];
    foreach ($notes as $key => $note) {
        $key  = preg_replace('/[^a-f0-9]/', '', (string) $key);
        $note = str_trim_to(trim((string) $note), 400);
        if ($key !== '' && $note !== '') {
            $clean[$key] = $note;
        }
    }
    db_run(
        'UPDATE components SET notes_override = ?, updated_by = ? WHERE id = ?',
        [json_encode($clean, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE), $userId, $id]
    );
    component_after_write($id, $userId);
}
