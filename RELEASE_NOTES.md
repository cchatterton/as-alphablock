Fixes the reverse FAQ relationship query introduced in v3.1.5. On the FAQ Demo page, AlphaBlock rendered an empty list even though a published FAQ visibly links to that page. The earlier query assumed the relationship was stored as a serialized array of string IDs.

### Existing block configuration

No JSON change is needed. A `CPT` filter with `key: "related_to"` or `key: "relates_to"` and `value: "%me%"` now checks the IDs returned from each published FAQ's `relates_to` meta by WordPress. This supports serialized arrays of integers or strings and separate scalar meta rows. The comparison uses complete IDs, avoiding partial matches.

The `Meta` filter that displays content selected on an FAQ remains unchanged.

### Installation

Extract `as-alphablock-3.1.6.zip` into your active theme's `blocks/` directory, replacing the existing AlphaBlock files. This is a theme component requiring the shared AlphaSys block loader, ACF PRO, `block_classes()`, Magic Card and theme layout assets.

### Validation

- PHP syntax checks pass for both block PHP files; both block JSON files parse successfully.
- Simulated reverse queries match decoded integer arrays, string arrays and separate meta rows, without partial ID matches.
- The prior FAQ-to-content relationship behavior still passes its simulated render.
- The release ZIP contains the four block source files.
- Live WordPress integration can only be confirmed after deploying this release to the site.
