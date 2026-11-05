Move the Posts per row note into a full-width help area beneath all **Show Posts** controls, matching the other tabs. The individual Posts per row input no longer has a note beneath it.

### File-by-file update from v3.3.4

- `alphablock/alphablock.json`: move the note and advance the ACF field-group modification timestamp.
- `alphablock/block.json`: component version 3.3.5.

Sync the AlphaBlock ACF field group from local JSON if the note remains in its old position. No PHP files changed; the -1 row behaviour from v3.3.4 is retained.

### Validation

All nine editor/schema tests pass, including full-width note placement after Show Links and before the next tab. JSON and ZIP integrity checks pass. Query/rendering code is unchanged from v3.3.4, which passed 142 WordPress/database assertions.
