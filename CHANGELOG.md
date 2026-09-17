# Changelog

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
