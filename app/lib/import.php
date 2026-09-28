<?php
declare(strict_types=1);

/**
 * Importing a component from Figma, by paste.
 *
 * One box. What was pasted is worked out rather than chosen from a menu, because
 * a menu asks someone to classify their own clipboard before they have looked at
 * it, and they will get it wrong in the direction that loses work.
 *
 * Nothing here fetches anything. Figma's REST API would need a token and a file
 * that stays shared; a node link is kept as a link and its file and node read out
 * of it, so the API can be added later without a migration.
 *
 * Nothing here saves anything either. Dev Mode's output is close but rarely
 * shippable — absolute positioning, generated class names, hardcoded hex where a
 * token exists — so it lands as a proposal to be looked at and accepted, beside
 * what is already there. See section 6 of docs/COMPONENTS.md.
 */

/** What a paste turned out to be, with what was pulled out of it. */
function import_blank(): array
{
    return [
        'kind'    => 'unknown',
        'label'   => 'Not recognised',
        'note'    => '',
        'markup'  => '',
        'css'     => '',
        'figma'   => null,
        'refused' => false,
    ];
}

/**
 * Figma's own clipboard.
 *
 * Copying a component in Figma puts an HTML fragment on the clipboard carrying
 * two base64 blobs in comments: `figmeta`, and `figma` — the document in Figma's
 * internal kiwi-encoded format. Decoding it would mean writing a kiwi decoder in
 * PHP against a schema Figma changes without notice, which would break silently
 * and be wrong in ways nobody could see.
 *
 * So it is recognised and refused, with an explanation of what to paste instead.
 * It WILL get pasted — it is the obvious thing to try — and a blank result or a
 * shrug would send someone away thinking the feature is broken.
 */
function import_is_figma_clipboard(string $text): bool
{
    return str_contains($text, 'figmeta')
        || str_contains($text, '(figma)')
        || str_contains($text, 'data-buffer=');
}

/**
 * Reads a paste and says what it is.
 *
 * The order matters: the binary clipboard is checked before anything else
 * because it is an HTML fragment and would otherwise be taken for markup, and a
 * bare link before markup because a URL is not HTML but is easily mistaken for
 * nothing in particular.
 */
function component_import_detect(string $text): array
{
    $out  = import_blank();
    $text = trim($text);

    if ($text === '') {
        $out['kind']  = 'empty';
        $out['label'] = 'Nothing pasted';
        $out['note']  = 'Paste a Figma link, the code from Dev Mode, some CSS, or an SVG.';
        return $out;
    }

    // 1. Figma's binary clipboard. First, because it arrives as HTML.
    if (import_is_figma_clipboard($text)) {
        $out['kind']    = 'figma-clipboard';
        $out['label']   = 'Figma\'s own clipboard';
        $out['refused'] = true;
        $out['note']    = 'This is Figma\'s internal format — an encoded copy of the '
                        . 'document, not code. It cannot be read outside Figma. '
                        . 'Instead: open Dev Mode, copy the generated code, and paste '
                        . 'that; or right-click the component and Copy link to selection.';
        return $out;
    }

    // 2. A link on its own.
    if (preg_match('~^https?://\S+$~i', $text)) {
        $figma = component_figma_parse($text);
        $out['kind']  = 'figma-link';
        $out['figma'] = $figma;
        if ($figma['url'] === null) {
            $out['label']   = 'A link that cannot be followed';
            $out['refused'] = true;
            $out['note']    = 'Only http and https links are kept.';
            return $out;
        }
        if ($figma['file_key'] === null) {
            $out['label'] = 'A link, but not a Figma one';
            $out['note']  = 'It will be kept as the component\'s link. Nothing is read from it.';
            return $out;
        }
        $out['label'] = 'A Figma link';
        $out['note']  = $figma['node_id'] !== null
            ? 'File and node read from it. Nothing is fetched — the link is stored so the '
              . 'component can be opened in Figma, and so a sync can be added later.'
            : 'It points at the whole file rather than a selection. Right-click the '
              . 'component and Copy link to selection to capture the node as well.';
        return $out;
    }

    // 3. An SVG. The honest path for an icon or an illustration: the geometry is
    //    exact and there is nothing to tidy.
    if (stripos($text, '<svg') !== false) {
        $out['kind']   = 'svg';
        $out['label']  = 'An SVG';
        $out['markup'] = $text;
        $out['note']   = 'Used as the variant\'s markup as it is. Worth deleting any '
                       . 'width and height so it scales, and checking the fills are '
                       . 'currentColor where they should follow the text.';
        return $out;
    }

    // 4. Dev Mode: markup with its CSS, in one paste or in a <style>.
    $css    = '';
    $markup = $text;

    if (preg_match_all('~<style[^>]*>(.*?)</style>~is', $text, $m)) {
        $css    = trim(implode("\n\n", $m[1]));
        $markup = trim(preg_replace('~<style[^>]*>.*?</style>~is', '', $text) ?? '');
    }

    $hasTag = preg_match('~<[a-z][a-z0-9-]*[\s>/]~i', $markup) === 1;
    $looksLikeCss = preg_match('~[^{}]*\{[^{}]*:[^{}]*\}~s', $markup) === 1;

    if ($hasTag && $css !== '') {
        $out['kind']   = 'devmode';
        $out['label']  = 'Markup and CSS';
        $out['markup'] = $markup;
        $out['css']    = $css;
        $out['note']   = 'Check it before accepting. Dev Mode writes absolute positions, '
                       . 'generated class names and hardcoded colours where a token exists — '
                       . 'the spec below flags every value that is not one of the brand\'s.';
        return $out;
    }

    // Markup and CSS pasted together with no <style> between them: the CSS is
    // whatever follows the last closing tag and looks like rules.
    if ($hasTag && $looksLikeCss && preg_match('~(.*>)\s*([^>]*\{[^{}]*:[^{}]*\}.*)$~s', $markup, $m)) {
        $out['kind']   = 'devmode';
        $out['label']  = 'Markup and CSS';
        $out['markup'] = trim($m[1]);
        $out['css']    = trim($m[2]);
        $out['note']   = 'The markup and the rules were separated at the last closing tag. '
                       . 'Check the split is where you would have put it.';
        return $out;
    }

    if ($hasTag) {
        $out['kind']   = 'html';
        $out['label']  = 'Markup';
        $out['markup'] = $markup;
        $out['note']   = $css !== ''
            ? 'With the CSS from its <style>.'
            : 'No CSS came with it, so it will render with whatever the component already has.';
        $out['css'] = $css;
        return $out;
    }

    if ($css !== '' || $looksLikeCss) {
        $out['kind']  = 'css';
        $out['label'] = 'CSS';
        $out['css']   = $css !== '' ? $css : $markup;
        $out['note']  = 'Added to the component\'s base CSS, which every variant shares.';
        return $out;
    }

    $out['note'] = 'It is not a link, markup, CSS or an SVG. If it came from Figma, use '
                 . 'Dev Mode\'s generated code rather than copying the component itself.';
    return $out;
}

/**
 * Applies an accepted proposal.
 *
 * Everything is additive or explicit: CSS is appended rather than replacing what
 * is there, because an import is usually one more state rather than a fresh
 * start, and someone who wanted a replacement can clear the field first. The
 * markup goes to a named variant, existing or new, chosen on the review screen
 * rather than guessed at.
 *
 * Returns where to send the person, which is the variant they just filled.
 */
function component_import_apply(int $id, array $fields, ?int $userId): ?int
{
    $component = component_find($id);
    if (!$component) {
        return null;
    }

    $markup = (string) ($fields['markup'] ?? '');
    $css    = trim((string) ($fields['css'] ?? ''));
    $link   = trim((string) ($fields['figma_url'] ?? ''));

    if ($link !== '') {
        $figma = component_figma_parse($link);
        db_run(
            'UPDATE components SET figma_url = ?, figma_file_key = ?, figma_node_id = ? WHERE id = ?',
            [$figma['url'], $figma['file_key'], $figma['node_id'], $id]
        );
    }

    if ($css !== '') {
        $existing = trim((string) $component['base_css']);
        component_save_css($id, $existing === '' ? $css : $existing . "\n\n" . $css, $userId);
    }

    if (trim($markup) === '') {
        return $link !== '' || $css !== '' ? 0 : null;
    }

    $target = (int) ($fields['variant'] ?? 0);
    if ($target > 0 && component_variant_find($id, $target)) {
        $variant = component_variant_find($id, $target);
        component_variant_save($id, $target, [
            'label'       => $variant['label'],
            'markup'      => $markup,
            'variant_css' => $variant['variant_css'],
            'notes'       => $variant['notes'],
        ], $userId);
        return $target;
    }

    $label = str_trim_to(trim((string) ($fields['label'] ?? '')), 190) ?: 'Imported';
    $new = component_variant_create($id, $label, $userId);
    component_variant_save($id, $new, [
        'label'       => $label,
        'markup'      => $markup,
        'variant_css' => '',
        'notes'       => '',
    ], $userId);
    return $new;
}
