AlphaBlock v3.2.0 orders the template into three clear phases: `1. GET RAW VALUES`, `2. REPLACE TOKENS`, and `3. PROCESS`. Raw values and owning post context are gathered first. Each token has an independent replacement block. Normalization, preview, query construction and rendering happen only after replacement, without checking whether a value came from a token. `%me%`, `%mine%` and numeric CPT ID conversion retain their v3.1.11 behavior.

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

Extract `as-alphablock-3.2.0.zip` into your active theme's `blocks/` directory, replacing the existing AlphaBlock files. This is a theme component requiring the shared AlphaSys block loader, ACF PRO, `block_classes()`, Magic Card and theme layout assets.

### Validation

- PHP syntax checks pass for both block PHP files; both block JSON files parse successfully.
- Simulated renders verify that `%me%` becomes the owning post ID, `%mine%` reads owner meta, and token and literal IDs produce identical CPT, relationship and Family queries. Editor preview and malformed filter handling were also checked.
