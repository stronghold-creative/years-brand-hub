<?php
declare(strict_types=1);

/**
 * Character widths for More Years, in em, taken from the font itself.
 *
 * A fitted headline needs to know how wide a line will be before the browser
 * draws it. Measuring in JavaScript means a visible reflow on every load, so
 * the widths are read from the font at build time and the size is worked out
 * here instead. Kerning is ignored; headlines are short and the error is well
 * under a percent, which the safety margin below absorbs.
 *
 * Regenerate with tools/font-metrics.php if the font is ever updated.
 */
function more_years_widths(): array
{
    return [
    ' ' => 0.3,
    '!' => 0.272,
    '"' => 0.413,
    '#' => 0.741,
    '$' => 0.469,
    '%' => 0.7,
    '&' => 0.491,
    '\'' => 0.184,
    '(' => 0.305,
    ')' => 0.305,
    '*' => 0.335,
    '+' => 0.379,
    ',' => 0.241,
    '-' => 0.387,
    '.' => 0.215,
    '/' => 0.485,
    '0' => 0.649,
    '1' => 0.389,
    '2' => 0.565,
    '3' => 0.598,
    '4' => 0.594,
    '5' => 0.594,
    '6' => 0.62,
    '7' => 0.502,
    '8' => 0.612,
    '9' => 0.622,
    ':' => 0.223,
    ';' => 0.248,
    '<' => 0.33,
    '=' => 0.36,
    '>' => 0.342,
    '?' => 0.514,
    '@' => 0.792,
    'A' => 0.688,
    'B' => 0.572,
    'C' => 0.639,
    'D' => 0.571,
    'E' => 0.549,
    'F' => 0.545,
    'G' => 0.654,
    'H' => 0.678,
    'I' => 0.336,
    'J' => 0.612,
    'K' => 0.635,
    'L' => 0.479,
    'M' => 0.854,
    'N' => 0.682,
    'O' => 0.719,
    'P' => 0.575,
    'Q' => 0.759,
    'R' => 0.567,
    'S' => 0.566,
    'T' => 0.567,
    'U' => 0.737,
    'V' => 0.641,
    'W' => 0.866,
    'X' => 0.678,
    'Y' => 0.649,
    'Z' => 0.545,
    '[' => 0.364,
    '\\' => 0.477,
    ']' => 0.364,
    '^' => 0.354,
    '_' => 0.479,
    '`' => 0.209,
    'a' => 0.618,
    'b' => 0.581,
    'c' => 0.52,
    'd' => 0.622,
    'e' => 0.552,
    'f' => 0.386,
    'g' => 0.602,
    'h' => 0.559,
    'i' => 0.272,
    'j' => 0.274,
    'k' => 0.573,
    'l' => 0.256,
    'm' => 0.845,
    'n' => 0.561,
    'o' => 0.6,
    'p' => 0.624,
    'q' => 0.589,
    'r' => 0.502,
    's' => 0.455,
    't' => 0.387,
    'u' => 0.61,
    'v' => 0.555,
    'w' => 0.727,
    'x' => 0.547,
    'y' => 0.604,
    'z' => 0.461,
    '{' => 0.323,
    '|' => 0.219,
    '}' => 0.325,
    '~' => 0.411,
    '£' => 0.657,
    'á' => 0.618,
    'è' => 0.555,
    'é' => 0.555,
    'ö' => 0.6,
    'ü' => 0.61,
    '‘' => 0.184,
    '’' => 0.184,
    '“' => 0.423,
    '”' => 0.43,
    ];
}

/** Anything not in the table, including emoji and unusual punctuation. */
function more_years_fallback_width(): float
{
    return 0.63;   // the average capital
}

/**
 * How wide a line is, in em, at font-size 1.
 * Tracking is added per character because it accumulates across the line.
 */
function hero_line_width(string $text, float $tracking = -0.03): float
{
    $widths = more_years_widths();
    $total = 0.0;
    $count = 0;

    foreach (preg_split('//u', $text, -1, PREG_SPLIT_NO_EMPTY) as $char) {
        $total += $widths[$char] ?? more_years_fallback_width();
        $count++;
    }

    // Tracking applies between characters, not after the last one.
    $total += $tracking * max(0, $count - 1);
    return max(0.5, $total);
}
