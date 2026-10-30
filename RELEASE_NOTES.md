Restore the tab label to **Prompt**, as originally intended. The request input remains empty, with its example as placeholder text. The system instructions remain separate.

### File-by-file update from v3.3.1

- `alphablock/alphablock.json`: restores the tab label and advances the ACF JSON modification timestamp.
- `alphablock/block.json`: updates the component version to 3.3.2.

No PHP files changed. Upload the updated JSON files and sync the ACF field group if the old label remains.

### Validation

All six editor/schema tests pass. Both JSON files parse and the published ZIP is checked against the repository source. Query/rendering code is unchanged from the v3.3.1 build, which passed 95 WordPress/database checks, including 40 render comparisons against supplied 3.2.1.
