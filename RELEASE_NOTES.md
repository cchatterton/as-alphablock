AlphaBlock v3.4.1 updates the Block Title controls.

### Header Tag and Header Class

The Header Level slider is now a Header Tag select with H1–H6, span and p. Header Class is an optional input for space-separated CSS classes on the title element. Classes and title text are escaped in rendered HTML.

### Existing blocks

The original `field_header_level` key and `header_level` JSON property are retained. Existing numeric values 1–6 remain selected in the new control and render the same H1–H6 element. The new span and p choices store their tag names in that property. A missing Header Class produces the previous markup without a class attribute. No content migration is needed.

### Install

Extract the complete `alphablock/` folder from `as-alphablock-3.4.1.zip` into the active theme's `blocks/` directory. Sync the AlphaBlock ACF field group from its local JSON so the new controls appear. This theme component does not deploy itself to a live site.

For a file-by-file update from 3.4.0, upload `prompt.php`, `block.php`, `template.php`, `alphablock.json`, then `block.json`. `args.php` and `tokens.php` are unchanged.

### Validation

The disposable WordPress/SQLite suite passes 285 checks, including numeric-heading compatibility and new tag/class rendering. Seventeen editor synchronization tests pass. PHP lint, JSON parsing, ZIP contents and checksum are verified. The production ACF block editor and active theme still need a staging smoke test.
