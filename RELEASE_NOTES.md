AlphaBlock v3.4.0 adds a **Show as: Card / List** toggle on the Show Posts tab.

### Backwards compatibility

**Card remains the default.** Missing or invalid `show_as` values use the existing Card renderer. Existing queries, card templates, markup, CSS classes and layouts are retained. A card template named List still renders as a card. No content migration is required.

### New List mode

Choose List to display each selected published post's title linked to its permalink. The four new inputs are all optional:

- UL class (`list_ul_class`)
- LI class (`list_li_class`)
- A class (`list_a_class`)
- Font Awesome icon (`list_icon`), for example `fa-solid fa-chevron-right`

List outputs plain UL/LI/A markup. Classes are added only when supplied. A decorative icon replaces normal bullets; without an icon, normal list bullets remain. The theme must already load Font Awesome. List does not require a card template and does not use card layout classes, row widths or carousel duplicates. Existing filters, ordering, Family, Build, manual selections and debug still determine its posts.

Both modes' settings are retained when switching or reopening a block. Guidance stays beneath all Show Posts inputs. ACF/Select2 synthetic changes are handled so a changed card selection is reflected in JSON. The system prompt documents the new properties while preserving cleanup of the exact text mistakenly prefilled by 3.3.0. The request input stays empty.

### File-by-file update from 3.3.5

Upload in this order:

1. `alphablock/prompt.php`
2. `alphablock/block.php`
3. `alphablock/template.php`
4. `alphablock/alphablock.json`
5. `alphablock/block.json`

Sync the AlphaBlock ACF field group from local JSON if the toggle does not appear. No changes to `args.php` or `tokens.php`. The ZIP contains the complete seven-file deployable folder. Publishing this release does not deploy to a live site.

For rollback, existing Card blocks retain their prior format. Newly configured List blocks need to be switched to Card with a selected template, or restored from a configuration backup, before using an older renderer.

### Validation

274 WordPress/database assertions pass, including 40 complete render comparisons against supplied 3.2.1 and 80 against released 3.3.5. Sixteen editor/schema tests pass. Real-browser checks with ACF 6.8.11 confirm the toggle, conditional fields, retained values, guidance position and Select2-style change synchronization. PHP, JSON, whitespace and ZIP checks pass.

Tests use disposable WordPress/SQLite, substituted external card/theme functions, a simulated editor and an isolated ACF form. The production ACF PRO block editor, theme layouts and Font Awesome fonts still require a staging smoke test.
