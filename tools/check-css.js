/**
 * Two checks the handover asks for after any CSS work:
 *
 *  - braces balance
 *  - no selector sets the same property twice with different values in the
 *    same at-rule context
 *
 * The second one is here because a stray `.masthead { position: relative }`
 * once cancelled a `position: sticky` declared earlier, and two rounds of
 * JavaScript fixes chased a bug that was a duplicate declaration. A second
 * whole copy of the repeater styles was found the same way.
 *
 * Context matters: .reader redefining grid-template-columns inside a media
 * query is the point of a media query, not a bug. Only a collision at the same
 * nesting level is reported.
 *
 * Some hits are deliberate. The appearance tokens re-declare base rules with
 * var(--s-*) on purpose, later in the file, so that Appearance in the admin can
 * move them. Read the output; do not assume every line is a fault.
 *
 *   node tools/check-css.js public_html/assets/css/*.css
 */
const fs = require('fs');

// Unbalanced braces are wrong, full stop, so they fail. A repeated property may
// be deliberate, so it is reported and left to a person unless --strict is
// passed. That way this can gate a deploy without crying wolf.
let broken = 0;
let noted = 0;
const strict = process.argv.includes('--strict');

const files = process.argv.slice(2).filter((a) => !a.startsWith('--'));
if (!files.length) {
  console.error('Usage: node tools/check-css.js [--strict] <file.css> [...]');
  process.exit(2);
}

for (const file of files) {
  const css = fs.readFileSync(file, 'utf8').replace(/\/\*[\s\S]*?\*\//g, '');

  const open = (css.match(/\{/g) || []).length;
  const close = (css.match(/\}/g) || []).length;
  console.log(`${file}: ${open} { / ${close} }` + (open === close ? ' balanced' : '  *** UNBALANCED ***'));
  if (open !== close) broken++;

  // Walk the file tracking the stack of at-rules we are inside.
  const stack = [];
  const seen = new Map();        // "context||selector" -> Map(prop -> value)
  const media = new Map();       // selector -> Map(prop -> {value, context})
  let buffer = '';
  const problems = [];

  for (let i = 0; i < css.length; i++) {
    const ch = css[i];
    if (ch === '{') {
      const head = buffer.trim().replace(/\s+/g, ' ');
      buffer = '';
      if (head.startsWith('@')) {
        stack.push(head);
        continue;
      }
      // A rule block: read to its matching close brace.
      let depth = 1;
      let body = '';
      while (++i < css.length && depth > 0) {
        if (css[i] === '{') depth++;
        else if (css[i] === '}') { depth--; if (depth === 0) break; }
        body += css[i];
      }
      const context = stack.join(' >> ');
      const props = new Map();

      for (const decl of body.split(';')) {
        const c = decl.indexOf(':');
        if (c < 0) continue;
        const prop = decl.slice(0, c).trim().toLowerCase();
        const value = decl.slice(c + 1).trim().replace(/\s+/g, ' ');
        if (!prop || prop.startsWith('--') || prop.includes('{')) continue;
        if (props.has(prop) && props.get(prop) !== value) {
          problems.push(`SAME RULE, TWICE   ${context ? '[' + context + '] ' : ''}${head} { ${prop}: "${props.get(prop)}" then "${value}" }`);
        }
        props.set(prop, value);
      }

      // Each comma-separated selector is tracked on its own, so
      // ".a, .b { color }" then ".b { color }" is still caught.
      for (const one of head.split(',').map((s) => s.trim()).filter(Boolean)) {
        const key = context + '||' + one;
        if (!seen.has(key)) seen.set(key, new Map());
        const before = seen.get(key);
        for (const [prop, value] of props) {
          if (before.has(prop) && before.get(prop) !== value) {
            problems.push(`SELECTOR REPEATED  ${context ? '[' + context + '] ' : ''}${one} { ${prop}: "${before.get(prop)}" then "${value}" }`);
          }
          before.set(prop, value);
        }

        /**
         * A rule inside a media query, overridden by a later one outside it.
         *
         * This is the version of the duplicate-property trap that hurts most,
         * because it looks right: the media query says what should happen at
         * that width, and at equal specificity the later rule wins anyway, so
         * the responsive rule silently does nothing. It bit twice in one
         * sitting here — a drawer's tap targets and an opener's corners — and
         * the context-aware check above cannot see it, because the two rules
         * are deliberately in different contexts.
         */
        if (context) {
          media.set(one, media.get(one) || new Map());
          for (const [prop, value] of props) {
            media.get(one).set(prop, { value, context });
          }
        } else {
          const earlier = media.get(one);
          if (earlier) {
            for (const [prop, value] of props) {
              for (const [seenProp, seen] of earlier) {
                // The same property, or either one being the other's shorthand:
                // a later "padding" beats an earlier "padding-top" just as a
                // later "padding-top" beats an earlier "padding".
                const collides = seenProp === prop
                  || prop.startsWith(seenProp + '-')
                  || seenProp.startsWith(prop + '-');
                if (!collides || seen.value === value) continue;

                const what = seenProp === prop ? prop : `${seenProp} / ${prop}`;
                problems.push(
                  `OVERRIDES A MEDIA QUERY  ${one} { ${what} } set in [${seen.context}], `
                  + `then set again here with no query, which wins`
                );
              }
            }
          }
        }
      }
      continue;
    }
    if (ch === '}') { stack.pop(); buffer = ''; continue; }
    buffer += ch;
  }

  const unique = [...new Set(problems)];
  if (unique.length) {
    noted += unique.length;
    unique.forEach((p) => console.log('  ' + p));
  } else {
    console.log('  no property set twice at the same level');
  }
}

if (noted) {
  console.log(`\n${noted} repeated declaration(s). Some are deliberate: the`
    + ' appearance tokens re-declare base rules with var(--s-*) further down the'
    + ' file so Appearance in the admin can move them. Read each one.');
}
if (broken) {
  console.log(`\n${broken} file(s) have unbalanced braces.`);
}
process.exit(broken || (strict && noted) ? 1 : 0);
