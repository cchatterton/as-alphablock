Corrects the AlphaBlock post type dropdown from `story` / Story to `stories` / Stories, matching the supplied site's registered post type key.

### Installation and existing blocks

Extract `as-alphablock-3.1.1.zip` into your active theme's `blocks/` directory, replacing the existing AlphaBlock files. Sync the ACF field group from local JSON if required.

For existing affected blocks, change `"post_type": "story"` to `"post_type": "stories"` and save the page. This release does not migrate saved content or rename post types.

This remains a theme component requiring the shared AlphaSys block loader, ACF PRO, `block_classes()`, Magic Card and theme layout assets. The referenced `style.css` was not provided and remains external.

### Validation

- Both JSON files parsed successfully; the Stories choice maps to `stories`.
- Both PHP files pass syntax checks and remain unchanged from v3.1.0.
- Release ZIP contents match the repository's four block source files.
- No live WordPress integration testing performed.
