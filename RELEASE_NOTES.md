Adds a reverse FAQ relationship query. A block on a page can now show FAQs whose ACF `relates_to` field includes that page.

### Existing block configuration

The JSON using `post_type: "faq"`, `filter_type: "CPT"`, `key: "related_to"` and `value: "%me%"` works without changes. For this filter, `related_to` is accepted as an alias for the actual FAQ meta key `relates_to`. The renderer matches the current page ID inside the relationship field using a quoted `LIKE` value. `compare_as: "="` may remain as configured.

A block on an FAQ can still use a `Meta` filter on `relates_to` and `%me%` to display the content selected by that FAQ.

### Installation

Extract `as-alphablock-3.1.5.zip` into your active theme's `blocks/` directory, replacing the existing AlphaBlock files. This is a theme component requiring the shared AlphaSys block loader, ACF PRO, `block_classes()`, Magic Card and theme layout assets.

### Validation

- PHP syntax checks pass for both block PHP files.
- Both block JSON files parse successfully.
- Simulated rendering confirms that only FAQs containing the current page ID are selected, with no partial ID matches.
- The prior FAQ-to-content relationship behavior still passes its simulated render.
- The release ZIP contains the four block source files.
- No live WordPress integration testing was available.
