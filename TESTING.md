# Validation for 3.3.3

## WordPress database integration

`tests/run-wordpress.php` runs in a **disposable** WordPress install without ACF or Magic Card active. It creates fixture posts, metadata and users. Do not run it against a real site's database.

```sh
wp eval-file /absolute/path/to/as-alphablock/tests/run-wordpress.php --use-include --path=/absolute/path/to/disposable-wordpress
```

The suite uses real WordPress query code and a database. Only the external ACF getter, theme wrapper helper and card renderer are substituted. The supplied 3.2.1 template is preserved in `tests/fixtures/template-3.2.1.php` as the compatibility reference.

The release was checked using PHP 8.5.7, WordPress 7.1.4 and the official SQLite Database Integration drop-in. It passes **113 assertions**, including **40 complete legacy render comparisons** and a deterministic legacy random-order comparison.

Coverage includes:

- None/All/Any/Family output, null post types, both singular/plural registered type keys, related FAQ string/integer/scalar IDs, unmatched CPT lookups and partial-ID rejection.
- Owning-post context, runtime overrides, invalid JSON, malformed filter rows, preview summaries, wrapper/layout markup and carousel duplicates.
- Nested token substitution, legacy spellings/contextual metadata, extensibility, first-item array handling, missing/zero values and one-pass replacement.
- Build quotas, deduplication, row ordering, remaining gaps, early stopping before unused CPT lookups, manual selections, exclusions, zero/unlimited/invalid quotas and malformed rows.
- Attribute operators, safe values, unsupported keys/operators, mixed All/Any metadata/attributes, published-only visibility and unrelated query isolation.
- Empty NLP input, preservation of actual requests, and cleanup of the exact generated system prompt.
- Default-off debug output, final resolved arguments, per-step Build dumps, runtime overrides and unchanged selected cards.

## Editor and ACF schema

```sh
npm ci --prefix tests
npm test --prefix tests
```

Eight tests use jsdom and the actual editor synchronization script. They verify all existing saved ACF keys/names/types, tab arrangement, the new filter choice, token spelling/case, numeric-control token preservation, unrelated field edits, JSON-to-field synchronization and preservation of the JSON-only debug flag. `tests/fixtures/fields-3.2.0.json` is the unchanged repository field-group baseline.

These tests simulate field markup and do not load ACF PRO's widgets. They do not replace checking the real block editor on staging.

## Package

```sh
python3 scripts/build-release.py
```

The script builds a reproducible ZIP from the version in `alphablock/block.json`, verifies that its seven files match the source, and writes a SHA-256 checksum. The ZIP contains only the deployable `alphablock/` folder; tests and developer dependencies are excluded.

## Staging checks before production deployment

1. Back up the existing AlphaBlock folder and saved content/configurations.
2. Replace the complete block folder and sync the ACF JSON through the normal non-destructive workflow.
3. Open/save an existing Meta, Persona Targets, related-FAQ and Family block; check its JSON and frontend cards.
4. Exercise the actual ACF relationship/post-object/repeater controls, all new instructions, the editable Prompt and new token spellings.
5. Check Build priority, quotas, manual includes/excludes and new Post Attributes filters against representative content.
6. Check desktop/mobile Wrap, Swipe and Carousel with the real Magic Card framework and theme assets.
7. Check live-site integrations such as language filters and the production MySQL version.

No production site has been modified or tested by the local validation run. The existing `style.css` reference remains an external theme dependency.
