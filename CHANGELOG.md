# Changelog

## 3.1.5 — 2026-10-23

- Add the reverse FAQ relationship query: a `CPT` filter with `relates_to` and `%me%` finds FAQs whose ACF relationship includes the page containing the block.
- Accept `related_to` as an alias for `relates_to` in this filter so existing block JSON works. Use a quoted post ID with `LIKE` to avoid partial ID matches.
- Preserve the `Meta` filter behavior that renders posts selected on an FAQ.

## 3.1.4 — 2026-10-22

- Treat the Post type field's `- Select -` / JSON `null` value as WordPress `any` for every AlphaBlock query mode.
- Keep explicit post type choices as filters. The `relates_to` / `%me%` filter continues to use the selected posts' actual types.

## 3.1.3 — 2026-10-22

- Correct the direction of the `relates_to` filter: when the block is on an FAQ, read that FAQ's selected post IDs and render those posts.
- Query across post types for this filter, since selected content may be a page or another custom post type. An empty relationship returns no cards.
- Existing AlphaBlock JSON does not need to change. This corrects the reversed lookup introduced in v3.1.2.

## 3.1.2 — 2026-10-22

- Resolve `relates_to` Meta filters with `"value": "%me%"` against the owning post ID.
- Match that ID inside ACF's serialized relationship field with a quoted `LIKE` value, so existing AlphaBlock JSON can display the related FAQ cards.
- Keep other Meta filters and query settings unchanged.

## 3.1.1 — 2026-09-17

- Correct the post type dropdown from `story` / Story to `stories` / Stories, matching the registered post type key shown in the supplied site configuration.
- Document updating existing block JSON. No automatic content migration is performed.
- PHP rendering and editor synchronisation code are unchanged.

## 3.1.0 — 2026-09-17

Initial standalone AlphaSys repository release of the supplied AlphaBlock implementation. Source files are unchanged from `alphablock (3).zip`.

- ACF field editing with bidirectional JSON synchronisation.
- JSON-driven post queries and Magic Card rendering.
- Family references using `%me%`, hierarchy root `0`, or explicit post IDs.
- Explicit post inclusions/exclusions and desktop/mobile layout settings.
- Installation and dependency documentation.

The template retains its original internal version 3.1 and historical release notes. Repository version 3.1.0 normalises that version for this release.
