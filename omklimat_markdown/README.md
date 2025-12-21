# omklimat.se export → Markdown

This folder contains WordPress posts converted to Markdown with YAML frontmatter.

## Structure
- `posts/`   Published posts (`status: publish`)
- `drafts/`  Draft posts (`status: draft`)
- `index.json`  A machine-readable list of all entries + metadata.

## Frontmatter fields
- title, date, slug, status
- author (if present)
- categories, tags
- source_url (original WP link)
- wp_id (original WordPress post id)
- featured_image (URL, if detected via _thumbnail_id)

## Notes
- Body content is converted from HTML to Markdown.
- Images are NOT downloaded; the markdown keeps links/embeds pointing at the original URLs.
