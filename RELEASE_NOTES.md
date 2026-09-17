Initial standalone release of AlphaSys AlphaBlock, based on the supplied template version 3.1.

The four source files are preserved unchanged. Includes ACF field/JSON synchronisation, post and family queries, runtime overrides, and Magic Card rendering with desktop/mobile layout settings.

### Installation

Extract `as-alphablock-3.1.0.zip` into your active theme’s `blocks/` directory. The ZIP contains one `alphablock/` folder. This is a theme component, not a WordPress plugin upload.

Requires ACF PRO, the existing AlphaSys block loader, `block_classes()`, the Magic Card framework and card templates, and theme layout assets. The supplied block metadata references `style.css`, which was absent from the source archive.

### Validation

- PHP syntax checks passed for both PHP files.
- Both JSON files parsed successfully.
- Packaged block files verified byte-for-byte against the supplied archive.
- No live WordPress integration testing performed.
