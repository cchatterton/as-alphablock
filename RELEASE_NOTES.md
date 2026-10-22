Corrects the `relates_to` / `%me%` filter. When AlphaBlock is on an FAQ, it now renders the posts selected in that FAQ's ACF relationship field. Those posts may have different post types. This replaces the reversed lookup in v3.1.2.

### Existing block configuration

No JSON change is needed. The existing Meta filter with `key` set to `relates_to`, `value` set to `%me%` and `compare_as` set to `=` works. For this filter, the block queries all selected post types even if `post_type` is set to `faq`. An empty `relates_to` selection produces no cards.

### Installation

Extract `as-alphablock-3.1.3.zip` into your active theme's `blocks/` directory, replacing the existing AlphaBlock files. This is a theme component requiring the shared AlphaSys block loader, ACF PRO, `block_classes()`, Magic Card and theme layout assets.

### Validation

- PHP syntax checks pass for both block PHP files.
- Both block JSON files parse successfully.
- A simulated FAQ with selected content IDs renders cards for those IDs; an empty selection returns no cards.
- The release ZIP contains the four block source files.
- No live WordPress integration testing was available.
