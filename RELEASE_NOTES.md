AlphaBlock v3.3.4 adds **Posts per row = -1**, meaning all selected posts fit in the same row.

```json
"posts_per_row": -1
```

- The editor control now accepts -1 and explains the option.
- Row width uses the final selected-post count, including manual inclusions and Build results, before carousel duplicate copies.
- Swipe/Carousel fractional spacing is skipped for -1. Empty results use a safe row width of 1.
- The default remains 3. Existing positive values, queries, saved field keys and JSON property names are unchanged.

### File-by-file update from v3.3.3

- `alphablock/template.php`: preserve -1 and calculate the final row width.
- `alphablock/alphablock.json`: allow -1 in the existing control and add its instructions.
- `alphablock/block.json`: version 3.3.4.

No changes to block.php, tokens.php, args.php or prompt.php. Sync the AlphaBlock ACF field group from local JSON if the control still has a minimum of 1. No live-site deployment is performed by publishing this release.

### Validation

142 WordPress/database assertions and nine editor/schema tests pass. Coverage includes the 40 complete 3.2.1 render comparisons, plus 20 additional comparisons preserving fixed/invalid row-width behaviour across layouts. PHP syntax, JSON and ZIP integrity checks pass. Local tests use WordPress/SQLite and a simulated editor; the live theme CSS and ACF PRO controls still require a staging check.
