Fixes Meta filters using `relates_to` and `%me%` so they find FAQ posts whose ACF relationship field includes the page containing AlphaBlock. The existing block JSON can stay unchanged, including `"compare_as": "="`.

### Installation

Extract `as-alphablock-3.1.2.zip` into your active theme's `blocks/` directory, replacing the existing AlphaBlock files. This is a theme component requiring the shared AlphaSys block loader, ACF PRO, `block_classes()`, Magic Card and theme layout assets.

### Validation

- PHP syntax checks pass for both block PHP files.
- Both block JSON files parse successfully.
- The generated relationship Meta query uses the owning post ID as a quoted `LIKE` value; other Meta filters retain their existing comparison.
- The release ZIP contains the four block source files.
- No live WordPress integration testing was available.
