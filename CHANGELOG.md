# Changelog

## 3.3.0 — 2026-10-30

- Extract extensible bulk token replacement into tokens.php, conditional query construction into args.php, and default Prompt guidance into prompt.php.
- Add owner ID/slug aliases and named metadata tokens while retaining existing token behaviour.
- Implement ordered Build quotas, deduplication, early stopping and manual inclusion/exclusion rules.
- Add Post Attributes filters with safe attribute comparisons and mixed All/Any handling; retain published-only output.
- Add editor instructions; label CPT filters Persona Targets; combine Includes/Excludes into Manual Selection without changing saved data keys.
- Preserve raw tokens through editor field/JSON synchronization.
- Validate against supplied 3.2.1 with WordPress database and editor regression tests. See TESTING.md for scope and staging checks.

## 3.2.1 — supplied template baseline, 2026-10-29

- Replace %me% and %me->slug% throughout string input values in one pass.
- Supplied directly in this task; this entry records the compatibility reference, not a separate GitHub release.

## 3.2.0 — 2026-10-25

- Reorder the template into three sequential phases: get raw block values and owning post context, replace tokens, then normalize and process the values.
- Keep `%me%` and `%mine%` in independent replacement blocks. The processing phase has no token-specific checks.
- Preserve the existing query modes and rendered behavior, including Family references and editor preview.

## 3.1.11 — 2026-10-25

- Give `%me%` and `%mine%` separate, labeled replacement blocks in the template.
- Mark the exact place to add another independent token replacement before query processing.
- Keep query behavior unchanged from v3.1.10.

## 3.1.10 — 2026-10-25

- Replace `%me%` with the owning post ID for every filter and Family reference before query processing.
- Add `%mine%` for filter values. It reads the filter's meta key on the owning post and uses the first value when the field is an array.
- Convert a numeric CPT filter value to a slug at CPT lookup time, whether it came from a token or was hardcoded.
- Preserve the relationship Meta query and the existing None, All, Any, Build and Family query modes.

## 3.1.9 — 2026-10-25

- Replace `%me%` in filter values immediately after resolving the post that contains the block.
- Build relationship, Meta and CPT queries from the resulting values without checking whether they came from a token. A literal `relates_to` ID and `%me%` now build identical query arguments.
- Mark the three stages in the template: get values, replace tokens, process the query and render cards.

## 3.1.8 — 2026-10-25

- Resolve `%me%` from the post containing the block before building filters: ID for Meta and Family, slug for CPT lookups.
- Query posts whose `relates_to` metadata contains that ID when the filter is Meta with `compare_as: "="`. Match complete IDs in ACF serialized arrays or a single scalar ID.
- Keep the configured `post_type`, ordering and limit for the FAQ query. A CPT lookup that finds no post now matches no posts rather than leaving the main query unrestricted.
- Update the Related FAQs configuration guidance.

## 3.1.7 — 2026-10-23

- Restore the executable template behavior from v3.1.4, removing the reverse FAQ lookups added in v3.1.5 and v3.1.6.
- Add comments explaining the template's configuration, post context, query modes, filters, family selection and card rendering. No executable PHP logic differs from v3.1.4.
- Keep `block.php` and both block JSON files unchanged.

## 3.1.6 — 2026-10-23

- Fix the reverse FAQ lookup to compare IDs returned by WordPress from each FAQ's `relates_to` meta, rather than assuming one serialized string format.
- Support relationship values stored as serialized arrays of integer or string IDs and as separate meta rows. Compare whole IDs so `13196` does not match `113196`.
- Keep the forward FAQ-to-content query unchanged.

## 3.1.5 — 2026-10-23

- Add the reverse FAQ relationship query: a `CPT` filter with `relates_to` and `%me%` finds FAQs whose ACF relationship includes the page containing the block.
- Accept `related_to` as an alias for `relates_to` in this filter so existing block JSON works. Use a quoted post ID with `LIKE` to avoid partial ID matches.
- Preserve the `Meta` filter behavior that renders posts selected on an FAQ.

## 3.1.4 — 2026-10-22

- Treat the Post type field's `- Select -` / JSON `null` value as WordPress `any` for every AlphaBlock query mode.
- Keep explicit post type choices as filters. The `relates_to` / `%me%` filter continues to use the selected posts' actual types.

## 3.1.3 — 2026-10-22

- Correct the direction of the `relates_to` filter: when the block is on an FAQ, read that FAQ's selected post IDs and render those posts.
- Query across post types for this filter, since selected content may be a page or another custom post type. An empty relationship returns no cards.
- Existing AlphaBlock JSON does not need to change. This corrects the reversed lookup introduced in v3.1.2.

## 3.1.2 — 2026-10-22

- Resolve `relates_to` Meta filters with `"value": "%me%"` against the owning post ID.
- Match that ID inside ACF's serialized relationship field with a quoted `LIKE` value, so existing AlphaBlock JSON can display the related FAQ cards.
- Keep other Meta filters and query settings unchanged.

## 3.1.1 — 2026-09-17

- Correct the post type dropdown from `story` / Story to `stories` / Stories, matching the registered post type key shown in the supplied site configuration.
- Document updating existing block JSON. No automatic content migration is performed.
- PHP rendering and editor synchronisation code are unchanged.

## 3.1.0 — 2026-09-17

Initial standalone AlphaSys repository release of the supplied AlphaBlock implementation. Source files are unchanged from `alphablock (3).zip`.

- ACF field editing with bidirectional JSON synchronisation.
- JSON-driven post queries and Magic Card rendering.
- Family references using `%me%`, hierarchy root `0`, or explicit post IDs.
- Explicit post inclusions/exclusions and desktop/mobile layout settings.
- Installation and dependency documentation.

The template retains its original internal version 3.1 and historical release notes. Repository version 3.1.0 normalises that version for this release.
