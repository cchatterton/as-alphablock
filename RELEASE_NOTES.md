The Post type field's `- Select -` value now means WordPress `any` across AlphaBlock queries. In JSON, use `"post_type": null`. An explicit post type still limits ordinary queries to that type.

The FAQ `relates_to` / `%me%` filter from v3.1.3 continues to render the posts selected on the FAQ, using their actual post types. The JSON supplied for Related Content, with `post_type: null` and `query_logic: All`, works without further changes.

### Installation

Extract `as-alphablock-3.1.4.zip` into your active theme's `blocks/` directory, replacing the existing AlphaBlock files. Sync the ACF field group from local JSON if required. This is a theme component requiring the shared AlphaSys block loader, ACF PRO, `block_classes()`, Magic Card and theme layout assets.

### Validation

- PHP syntax checks pass for both block PHP files.
- Both block JSON files parse successfully.
- Simulated rendering confirms `post_type: null` becomes `any`, while selected FAQ relationships render their target posts.
- The release ZIP contains the four block source files.
- No live WordPress integration testing was available.
