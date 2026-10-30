# AlphaBlock

AlphaSys's ACF-based WordPress theme block for querying posts and rendering reusable Magic Card templates.

## Version 3.3.1

Version 3.3 adds separate token resolution, query construction and authoring guidance in `tokens.php`, `args.php` and `prompt.php`. Patch 3.3.1 corrects the NLP input so system instructions never pre-populate the request. It adds the Build waterfall and Post Attributes filters, and explains the controls in the editor. The compatibility baseline is the supplied template **3.2.1**; existing None, All, Any and Family behaviour is retained for their existing configurations.

**Build changes behaviour:** it previously ran the base query. With filter rows it now fills a quota in filter priority order. Review existing blocks using Build before deployment. Existing saved JSON does not need a schema migration.

## Installation and upgrade

1. Back up the currently deployed `blocks/alphablock/` folder and the content/configuration being edited.
2. Download `as-alphablock-3.3.1.zip` from the GitHub release.
3. Extract the complete `alphablock/` folder into your active theme's `blocks/` directory, replacing the existing files. Deploy **all seven files together**; the template and editor require the new helpers.
4. Keep the shared AlphaSys theme loader that loads ACF JSON and registers `blocks/*/block.json`.
5. Sync the AlphaBlock ACF field group from local JSON if the editor still shows the old layout. Its field-group key and saved field keys are preserved; its modification timestamp is updated. Do not use the old destructive ACF hard-reset snippet.
6. Smoke-test representative pages on staging, including an existing related-FAQ block, Family block, layouts, editor saves and any Build blocks, before promoting the folder to production.

This is a theme component, not a standalone plugin. It does not install an updater or deploy to live sites.

For rollback, restore the complete previous block folder. If you saved new Post Attributes filters or changed Build configurations, restore those saved configurations as well; older renderers do not implement the new behaviour.

## Files and processing

| File | Responsibility |
|---|---|
| `block.json` | Registration and component version for `acf/alphablock` |
| `alphablock.json` | ACF fields, tabs, labels and editor guidance |
| `block.php` | Bidirectional field/JSON editing and NLP request-field handling |
| `template.php` | Raw configuration, owning-post context, normalization, query execution and existing card markup |
| `tokens.php` | Token definitions and one bulk replacement pass |
| `args.php` | Base/conditional query construction, Family selection and Build step arguments |
| `prompt.php` | System instructions for a separate NLP authoring integration |

The pipeline is: load JSON and runtime overrides → resolve the owning post → replace tokens → normalize values → construct query arguments → execute queries → render cards. The editor preview remains a metadata summary and does not run card queries.

## Merge tokens

| Token | Value |
|---|---|
| `%me%`, `%me->ID%` | ID of the post containing the block |
| `%me-slug%`, legacy `%me->slug%` | Its slug |
| `%mine->meta_key%` | Named metadata on that post |
| Legacy `%mine%` | The filter's own metadata key in a filter value; the owner ID in `whose_family` |

Replacement visits string values at any depth, including tokens embedded in longer text. Keys and non-string types remain intact. Unknown tokens remain literal. Replacement text is not expanded again. Metadata arrays use their first item, `WP_Post` values use their ID, and missing/non-scalar metadata becomes an empty string. `whose_family` requires an ID or `0`, so use ID-valued tokens there.

Add another fixed token in `alphablock_get_tokens()` or extend its map without editing the template:

```php
add_filter('alphablock_tokens', function ($tokens, $post_id) {
    $tokens['%abc%'] = get_post_meta($post_id, 'example_value', true);
    return $tokens;
}, 10, 2);
```

Custom map values must be scalar strings/numbers. The editor preserves raw JSON token values, including where a typed control cannot display them, until the author explicitly changes the corresponding field. Token names and metadata-key case are retained.

## Queries

The unselected Post type option is labelled **Any post type** and continues to store `null`. WordPress determines which registered post types participate in `any`; the block renders published posts only. Explicit post type keys remain literal. Existing blocks using a valid `story` key are not renamed to `stories`; use the actual registered key for each site.

- **None:** base query using type, ordering and total.
- **All:** every filter must match. With no filters, use the base query.
- **Any:** at least one filter must match. With no filters, use the base query.
- **Build:** ordered waterfall described below.
- **Family:** additive selection of parents, children, siblings, uncles/aunties and grandparents. Reference `0` plus Children selects top-level posts. Existing hierarchy and exclusion rules are retained.

### Build waterfall

Each filter is an independent query. Results are sorted within that filter using the selected ordering, then appended in filter priority order. Subsequent queries exclude already selected IDs. Once the quota is full, no later filter query or CPT lookup runs. If the rows are exhausted early, return the posts found; do not run an unrestricted fallback.

`total_posts: -1` removes the quota and processes all filters. With no filter rows, use the base query. Malformed rows are skipped rather than treated as a base query.

In **Build only**, explicit inclusions have first priority, count towards the quota, retain their manual order and must be published. Exclusions win. In the other query modes, existing behaviour is retained: inclusions append after the main query's limit and can reintroduce an excluded post.

### Filter types and comparisons

The dropdown labels change without renaming existing stored types:

| Label | Stored `filter_type` | Key and value |
|---|---|---|
| Persona Targets | `CPT` | Related-post field; target slug or ID. Use `field_name&lookup_post_type` for a different lookup type. |
| Meta | `Meta` | Stored metadata key and value. Existing `relates_to` equality still matches whole IDs in relationship arrays. |
| Post Attributes | `Post` | Supported post property and comparison value. |

Comparisons are `=`, `!=`, `>`, `<`, `>=`, `<=`, `LIKE` and `NOT LIKE`. LIKE/NOT LIKE are substring comparisons. ACF multi-value relationship, checkbox and select fields commonly store serialized arrays: use LIKE to match a member. For a Meta string ID use a quoted value such as `"123"` to avoid matching `1234`. Persona Targets formats LIKE ID matches. Meta comparisons retain their existing WordPress storage semantics; numeric comparison types are not inferred automatically.

Post Attributes supports `ID`, `author`, `parent`, `post_status`, `title`, `slug`, `date` and `modified`; native names such as `post_author`, `post_parent`, `post_title`, `post_name`, `post_date` and `post_modified` also work (`status` aliases `post_status`). IDs are nonnegative numbers and reject LIKE operators. Date values use `YYYY-MM-DD HH:MM:SS`. Invalid attributes/operators match no posts. Post status filters **do not broaden published-only visibility** or expose private/draft content. Any can combine attributes and metadata, including posts with no matching metadata rows.

### Related FAQs

To show FAQs referring to the page containing the block:

```json
{
  "post_type": "faq",
  "query_logic": "All",
  "filters": [
    {"filter_type": "Meta", "key": "relates_to", "compare_as": "=", "value": "%me%"}
  ]
}
```

Other saved card/layout properties remain as configured. A numeric literal ID and the resolved owner token build the same relationship query. The lookup uses the owning post, not an unrelated global post.

## NLP request and system instructions

The NLP tab's **Your request** textarea is for the author's natural-language request. It starts empty; an example such as "Show all posts with state = nsw using the news card. Carousel on desktop, swipe on mobile." appears only as placeholder text. The saved ACF field key `field_prompt` and name `prompt` are unchanged.

`prompt.php` holds the separate system instructions returned by `alphablock_get_prompt()`; customize them through `alphablock_prompt`. They are never inserted as the author's request. If v3.3.0 saved an exact copy of the generated system instructions in that field, the load filter clears that value in the editor; it is persisted as empty on the next normal save. Other saved request text remains intact. No database-wide rewrite is performed.

The supplied component contains no NLP-to-JSON request handler or AI API connection. An authoring integration must submit the user's request with the separate system instructions, resolve actual card IDs and apply returned JSON. Typing a request alone does not currently generate block configuration.

## Dependencies and limits

Requires the existing theme loader, ACF PRO, `block_classes()`, `Magic_Card::get_card()`, card template posts and theme layout assets. Exact minimum WordPress/ACF versions have not been established. `block.json` references `style.css`, which was absent from the supplied archive and remains external.

Card rendering, wrapper classes and layout markup are retained. `show_links` remains unused by the renderer. The shared Magic Card framework and wrapper helper are external dependencies; their separate known issues are not changed by this component release.

See [TESTING.md](TESTING.md) for validation, [CHANGELOG.md](CHANGELOG.md) for history, and [RELEASE_NOTES.md](RELEASE_NOTES.md) for this release's upgrade notes. ACF documents its local JSON synchronization rules in [Synchronized JSON](https://www.advancedcustomfields.com/resources/synchronized-json/).
