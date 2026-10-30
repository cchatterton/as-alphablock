AlphaBlock v3.3.3 adds a manually controlled JSON debug flag and includes the Prompt tab correction from v3.3.2.

```json
"debug": 0
```

Change the value to `1` to print the final query arguments through the theme's existing `pre_dump($args)` function. Change it back to `0` to disable. The flag defaults to 0 for existing blocks and is retained by editor saves; there is no ACF toggle.

The dump follows token replacement, normalization and conditional argument construction. Build dumps each executed manual-selection/filter query with its actual remaining quota and exclusions. Unused rows and intermediate CPT/Family lookup queries are not dumped. Editor preview does not run queries or print their arguments. Debug does not change selected posts or card rendering.

### File-by-file update

From v3.3.2, upload only:

- `alphablock/template.php`: debug default and calls to the existing theme function.
- `alphablock/block.php`: preserve the JSON-only debug option through editor saves.
- `alphablock/block.json`: version 3.3.3.

If you have not yet applied v3.3.2, also upload `alphablock/alphablock.json` to restore the **Prompt** tab label, and sync the field group if needed. `prompt.php`, `tokens.php` and `args.php` have no new changes from v3.3.2.

The ZIP contains the full deployable folder. No live site deployment is performed by publishing this release.

### Validation

113 WordPress/database assertions, including 40 complete render comparisons against supplied 3.2.1, and eight editor/schema tests pass. PHP syntax, JSON and ZIP integrity checks pass. Tests use disposable WordPress/SQLite and simulated editor controls; the live theme/ACF PRO environment still needs its staging smoke test.
