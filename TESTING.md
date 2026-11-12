# Validation for 3.4.1

## WordPress database integration

`tests/run-wordpress.php` runs in a **disposable** WordPress install without ACF or Magic Card active. It creates fixture posts, metadata and users. Do not run it against a real site's database.

```sh
wp eval-file /absolute/path/to/as-alphablock/tests/run-wordpress.php --use-include --path=/absolute/path/to/disposable-wordpress
```

The suite uses real WordPress query code and a database. Only the external ACF getter, theme wrapper helper and card renderer are substituted. The supplied 3.2.1 template is preserved in `tests/fixtures/template-3.2.1.php` as the original compatibility reference. The released 3.3.5 template is also preserved, with only its two helper include paths adjusted. Its unchanged args.php/tokens.php helpers are hash-checked against the release snapshot.

The release was checked using PHP 8.5.7, WordPress 7.1.4 and the official SQLite Database Integration drop-in. It passes **285 assertions**, including **40 complete 3.2.1 render comparisons**, **80 complete 3.3.5 comparisons** (missing/explicit Card), and a deterministic legacy random-order comparison. The added checks cover numeric H1–H6 headings, span/p tags, optional classes, safe fallback for invalid tag and class input, and previous prompt cleanup.

Coverage includes:

- None/All/Any/Family output, null post types, both singular/plural registered type keys, related FAQ string/integer/scalar IDs, unmatched CPT lookups and partial-ID rejection.
- Owning-post context, runtime overrides, invalid JSON, malformed filter rows, preview summaries, wrapper/layout markup and carousel duplicates.
- Nested token substitution, legacy spellings/contextual metadata, extensibility, first-item array handling, missing/zero values and one-pass replacement.
- Build quotas, deduplication, row ordering, remaining gaps, early stopping before unused CPT lookups, manual selections, exclusions, zero/unlimited/invalid quotas and malformed rows.
- Attribute operators, safe values, unsupported keys/operators, mixed All/Any metadata/attributes, published-only visibility and unrelated query isolation.
- Empty NLP input, preservation of actual requests, and cleanup of the exact generated system prompt.
- Default-off debug output, final resolved arguments, per-step Build dumps, runtime overrides and unchanged selected cards.
- All-in-one-row sizing for -1, including manual inclusions, Build, empty results, carousel copies and 20 additional fixed/invalid-width render comparisons against 3.2.1.
- Default/invalid/explicit Card compatibility with 3.3.5 and no mode inference from a template named List.
- List post selection, classes, decorative icons, permalink/title escaping, hidden-post protection, empty results, Build/manual selections, debug and absence of card/carousel markup.

## Editor and ACF schema

```sh
npm ci --prefix tests
npm test --prefix tests
```

Seventeen tests use jsdom and the actual editor synchronization script. They verify existing ACF keys/names, the intentional Header Level type change, numeric heading preservation, span/class editing, tab arrangement, the new filter choice, token spelling/case, numeric-control token preservation, unrelated field edits, JSON-to-field synchronization, preservation of the JSON-only debug flag, saving -1 for posts per row, Card/List defaults and conditional definitions, mode switching, inactive settings, JSON edits, partial deployment, and Select2-style jQuery changes. `tests/fixtures/fields-3.2.0.json` is the unchanged repository field-group baseline.

These tests simulate field markup and do not load ACF PRO's widgets. They do not replace checking the real block editor on staging.

## Real-browser ACF check

Playwright was used against a disposable WordPress page rendering the Show Posts controls with official ACF **6.8.11**. Card was selected by default, including with a card template named List. Switching to List hid the card inputs and showed all four optional text inputs. Editing classes/icon and switching back preserved both sets of settings. Guidance remained below the controls. A Select2-style jQuery change was also checked against the real loaded ACF/Select2 widget and updated the JSON.

This check uses ACF's shared field types in an isolated form. It does not load the production ACF PRO block editor, the actual theme's CSS or Font Awesome fonts. The full staging checks below still apply. Screenshot artifacts are kept locally under ignored `output/playwright/`.

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
