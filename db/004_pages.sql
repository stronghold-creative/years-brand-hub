-- The agreed page inventory, created as drafts.
-- Safe to run more than once: a page whose slug already exists is skipped,
-- and nothing here touches a page you have already edited.

INSERT IGNORE INTO pages (slug, title, section, summary, status, sort_order, blocks) VALUES
('brand-in-a-sentence', 'Brand in a sentence', 'Start here', 'What Years is, in a form anyone can repeat without a deck.', 'draft', 0, '[]'),
('positioning', 'Positioning', 'Start here', 'Who we are for, what we stand against, and the claim we own.', 'draft', 1, '[]'),
('logo', 'Logo', 'Foundations', 'The marks, their variants, and the space around them.', 'draft', 2, '[]'),
('colour', 'Colour', 'Foundations', 'Every value, where each one goes, and which pairings are allowed.', 'draft', 3, '[]'),
('typography', 'Typography', 'Foundations', 'More Years and Poppins, the scale, and what each style is for.', 'draft', 4, '[]'),
('iconography', 'Iconography', 'Foundations', 'The set, its construction rules, and when to draw a new one.', 'draft', 5, '[]'),
('photography', 'Photography', 'Foundations', 'How food, dogs and people are shot, lit and cropped.', 'draft', 6, '[]'),
('illustration', 'Illustration', 'Foundations', 'When illustration is used instead of photography, and in what style.', 'draft', 7, '[]'),
('layout-and-grid', 'Layout and grid', 'Foundations', 'Columns, margins and the spacing scale everything sits on.', 'draft', 8, '[]'),
('motion', 'Motion', 'Foundations', 'How things move, how fast, and when they should not.', 'draft', 9, '[]'),
('accessibility', 'Accessibility', 'Foundations', 'The standard we hold to, and the checks before anything ships.', 'draft', 10, '[]'),
('tone-of-voice', 'Tone of voice', 'Voice', 'How Years sounds, and the qualities that define it.', 'draft', 11, '[]'),
('grammar-and-style', 'Grammar and style', 'Voice', 'Sentence case, numerals, punctuation, the small consistent decisions.', 'draft', 12, '[]'),
('vocabulary', 'Vocabulary', 'Voice', 'Words we use, words we avoid, and the ones with legal weight.', 'draft', 13, '[]'),
('product-naming', 'Product naming', 'Voice', 'How products, ranges and recipes are named and written.', 'draft', 14, '[]'),
('claims-and-regulatory-language', 'Claims and regulatory language', 'Voice', 'What we can say about the food, and what needs sign-off.', 'draft', 15, '[]'),
('worked-examples', 'Worked examples', 'Voice', 'Real copy, before and after, with the reasoning shown.', 'draft', 16, '[]'),
('packaging', 'Packaging', 'Applications', 'Sleeve architecture, hierarchy and the rules that are print-bound.', 'draft', 17, '[]'),
('social', 'Social', 'Applications', 'How the brand behaves organically across each platform.', 'draft', 18, '[]'),
('paid-ads', 'Paid ads', 'Applications', 'Creative rules for Meta and beyond, including hooks and end frames.', 'draft', 19, '[]'),
('email', 'Email', 'Applications', 'Structure, tone and the components available.', 'draft', 20, '[]'),
('presentations', 'Presentations', 'Applications', 'Deck structure and the template to start from.', 'draft', 21, '[]'),
('retail', 'Retail', 'Applications', 'Point of sale, shelf and trade materials.', 'draft', 22, '[]'),
('co-branding-and-partner-logos', 'Co-branding and partner logos', 'Standards', 'How our marks sit beside someone else''s, and who approves it.', 'draft', 23, '[]'),
('legal-marks-and-trademark-usage', 'Legal marks and trademark usage', 'Standards', 'Where the marks go, and how the name is written in legal contexts.', 'draft', 24, '[]'),
('generative-ai-usage', 'Generative AI usage', 'Standards', 'What AI may be used for, what it may not, and what must be disclosed.', 'draft', 25, '[]'),
('downloads', 'Downloads', 'Resources', 'Every file, current versions, in one place.', 'draft', 26, '[]'),
('templates', 'Templates', 'Resources', 'Starting points for decks, docs and social.', 'draft', 27, '[]'),
('figma-links', 'Figma links', 'Resources', 'Where the live files are and which are safe to work from.', 'draft', 28, '[]'),
('who-to-ask', 'Who to ask', 'Resources', 'Who owns what, and how to get something approved.', 'draft', 29, '[]');

-- A starting icon for each section, only if no icon map exists yet.
INSERT IGNORE INTO settings (name, value) VALUES ('section_icons', '{"Start here":"compass","Foundations":"layers","Voice":"speech","Applications":"box","Standards":"shield","Resources":"download"}');
