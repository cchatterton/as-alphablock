Restores AlphaBlock's v3.1.4 executable behavior and adds comments throughout `alphablock/template.php` to explain its major sections. The reverse FAQ lookup added in v3.1.5 and revised in v3.1.6 is removed.

### What this means for existing blocks

The FAQ-to-content `Meta` filter on `relates_to` and `%me%` remains as it was in v3.1.4. The Post type field's `- Select -` / JSON `null` value still means WordPress `any`.

The `CPT` filter with `related_to` or `relates_to` and `%me%` does not perform a reverse relationship lookup in this release. It follows the v3.1.4 CPT slug lookup behavior.

### Installation

Extract `as-alphablock-3.1.7.zip` into your active theme's `blocks/` directory, replacing the existing AlphaBlock files. This is a theme component requiring the shared AlphaSys block loader, ACF PRO, `block_classes()`, Magic Card and theme layout assets.

### Validation

- Executable PHP tokens in `alphablock/template.php` match v3.1.4 exactly after comments and whitespace are excluded.
- PHP syntax checks pass for both block PHP files; both block JSON files parse successfully.
- The existing FAQ-to-content simulated render passes.
- The release ZIP contains the four block source files.
