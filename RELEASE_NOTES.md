AlphaBlock v3.1.10 retains the three marked stages: get the values, replace tokens, and process the query and cards. `%me%` always becomes the ID of the post containing the block. `%mine%` in a filter reads that post's meta value using the filter key; for an array, it uses the first value. CPT lookup converts numeric IDs to slugs when needed, regardless of where the ID came from.

### Related FAQs

To list FAQs that refer to the page containing the block, configure:

```json
{
  "post_type": "faq",
  "query_logic": "All",
  "filters": [
    {
      "filter_type": "Meta",
      "key": "relates_to",
      "compare_as": "=",
      "value": "%me%"
    }
  ]
}
```

The key is `relates_to`, matching the FAQ meta field. The query matches the whole page ID in ACF relationship arrays or as a single ID. Other card and layout options can remain in the block JSON. A hardcoded ID such as `13196` builds the same query as `%me%` when the block is on post `13196`.

### Installation

Extract `as-alphablock-3.1.10.zip` into your active theme's `blocks/` directory, replacing the existing AlphaBlock files. This is a theme component requiring the shared AlphaSys block loader, ACF PRO, `block_classes()`, Magic Card and theme layout assets.

### Validation

- PHP syntax checks pass for both block PHP files; both block JSON files parse successfully.
- Simulated renders verify that `%me%` becomes the owning post ID, `%mine%` reads owner meta, and token and literal IDs produce identical CPT and relationship queries.
