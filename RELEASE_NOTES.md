AlphaBlock v3.1.8 resolves `%me%` from the post containing the block before query filters run. Meta filters use that post's ID; CPT filters use its slug; Family uses its ID.

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

The key is `relates_to`, matching the FAQ meta field. The query matches the whole page ID in ACF relationship arrays or as a single ID. Other card and layout options can remain in the block JSON. This changes the v3.1.7 `relates_to` Meta behavior, which read selected posts from the containing FAQ.

### Installation

Extract `as-alphablock-3.1.8.zip` into your active theme's `blocks/` directory, replacing the existing AlphaBlock files. This is a theme component requiring the shared AlphaSys block loader, ACF PRO, `block_classes()`, Magic Card and theme layout assets.

### Validation

- PHP syntax checks pass for both block PHP files; both block JSON files parse successfully.
- Simulated renders verify that Meta filters use the owning post ID, CPT filters use its slug, and `relates_to` builds exact ID conditions across ACF storage formats.
