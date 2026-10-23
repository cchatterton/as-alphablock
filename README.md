# AlphaBlock

AlphaSys’s ACF-based WordPress block for querying posts and rendering them through reusable Magic Card templates.

## Release

The current repository release is **v3.1.5**. It supports both directions of the FAQ relationship: a `Meta` filter renders the posts selected on an FAQ, and a `CPT` filter finds FAQs that select the page containing the block. The Post type field's `- Select -` value (`"post_type": null` in JSON) means WordPress `any`.

## Installation

1. Download `as-alphablock-3.1.5.zip` from the GitHub release.
2. Extract its `alphablock/` folder into your active theme’s `blocks/` directory.
3. Use the existing AlphaSys theme framework to load ACF JSON and register `blocks/*/block.json`.
4. Select a Card and configure AlphaBlock in the block editor.

This is a theme block component, not an independently installable WordPress plugin.

### Related FAQs

For an FAQ's multi-select ACF `relates_to` field, use `query_logic` of `Any` or `All` and a Meta filter with `key` set to `relates_to` and `value` set to `%me%`. The renderer reads the IDs selected on that FAQ and renders those posts. The existing `compare_as` value can remain `=`. Set `post_type` to `null` to represent `- Select -`; for this relationship filter, the selected posts' actual types are used. Ordering, limits, exclusions and Card selection still apply.

To show FAQs that include the current page in their `relates_to` field, set `post_type` to `faq` and use a `CPT` filter with `key` set to `relates_to` and `value` set to `%me%`. The earlier `related_to` spelling is accepted as an alias for this reverse query. The renderer matches the current page ID inside each FAQ's relationship field. The existing `compare_as` value can remain `=`; the renderer uses a quoted ID and `LIKE` for the reverse lookup.

### Updating existing Story blocks

For a site whose registered post type key is `stories`, change existing block JSON from `"post_type": "story"` to `"post_type": "stories"`, then save the page. This release changes the dropdown choice; it does not migrate saved blocks or rename registered post types. If the old dropdown remains, check whether the stored ACF field group needs syncing from the updated local JSON. Do not use the one-off hard-reset snippet to perform this update.

## Dependencies

- WordPress and ACF PRO with support for the supplied block metadata (`apiVersion: 3`, ACF `blockVersion: 3`). Exact minimum versions have not been validated.
- The shared AlphaSys ACF Blocks loader in the theme.
- The theme’s `block_classes()` helper.
- `Magic_Card::get_card()` and the `card` custom post type, including card templates stored in `card_markup` metadata.
- Theme CSS and any JavaScript needed for card layouts. `block.json` references `style.css`, but that file was not present in the supplied archive and is not bundled here.

The shared loader, wrapper helper and Magic Card framework remain external dependencies. This repository does not include the one-off ACF database reset snippet.

## Structure

- `alphablock/block.json`: registration metadata for `acf/alphablock`.
- `alphablock/alphablock.json`: ACF field group.
- `alphablock/block.php`: editor synchronisation between ACF fields and the JSON textarea.
- `alphablock/template.php`: JSON configuration, runtime overrides, post queries and card rendering.

## Behaviour

The renderer reads the ACF `json` field, applies defaults, then applies runtime overrides from `$block['attrs']['data']`. Editors can change fields or edit the JSON representation.

Queries support ordinary post selection, All/Any metadata and related-post filters, family relationships, ordering, and explicit includes/excludes. Layout settings cover Wrap, Swipe and Carousel for desktop and mobile. Cards are rendered using the selected card post’s slug.

## Baseline limitations

- `Build` currently executes the base query without additional filter-building logic.
- `show_links` is configured but is not used by the supplied renderer.
- The Prompt field is present; no prompt execution is implemented in these files.
- The editor synchronisation script includes console diagnostics.
- This release preserves the supplied implementation; it has not been integration-tested in a live WordPress theme.

See [CHANGELOG.md](CHANGELOG.md) for release history.
