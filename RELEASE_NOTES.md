AlphaBlock v3.3.0 separates token resolution, conditional query construction and prompt guidance, adds the Build waterfall and Post Attributes filters, and clarifies the editor controls.

- `tokens.php`: one early replacement pass; `%me%`, `%me->ID%`, `%me-slug%`, `%mine->meta_key%`, and existing aliases/contextual `%mine%`.
- `args.php`: existing query construction plus independent, ordered Build steps and scoped Post Attributes comparisons.
- `prompt.php`: default authoring guidance for empty Prompt fields; saved custom prompts remain intact.
- Post Query, Query Logic and Family guidance explain types, modes, comparisons, array matching and tokens.
- Persona Targets keeps stored type `CPT`; Meta stays `Meta`; Post Attributes uses the new type `Post`.
- Manual Selection replaces the two presentation tabs, retaining Include/Exclude field keys and JSON names.
- Raw JSON tokens survive editor synchronization, including tokens that typed controls cannot represent.

### Behaviour and compatibility

Existing None, All, Any and Family configurations are compared against the supplied 3.2.1 template; their query results and markup are retained in the regression fixtures. Existing field keys, JSON property names, null/any post type, card calls and layout markup remain supported.

**Build intentionally changes behaviour.** It previously ran the base query; it now fills the quota through filter rows in priority order. Manual inclusions have first priority and count towards the quota, with exclusions winning. Later rows never run once full. `-1` means unlimited. No rows means base-query fallback; exhausting configured filters does not trigger a fallback. Other modes retain their additive inclusion behaviour. Review existing saved Build blocks before deployment.

Post Attributes supports IDs, author, parent, post status, title, slug, date and modified date. Public output remains restricted to published posts; status filters do not expose drafts or private content.

### Install and rollback

Back up the deployed folder and content, then extract `as-alphablock-3.3.0.zip` into the active theme's `blocks/` directory. **Replace the complete AlphaBlock folder: all seven files are required.** Sync the ACF field group from local JSON if needed to display the new layout. No content migration or destructive ACF reset is required.

This remains a theme component requiring ACF PRO, the shared block loader, `block_classes()`, Magic Card and theme layout assets. No live site deployment is performed by this release.

Rollback restores the previous complete folder. Restore saved configuration backups too if you have begun using the new Post Attributes filters or changed Build configurations.

### Validation

- 92 WordPress/database assertions, including 40 complete render comparisons with supplied 3.2.1.
- Five editor DOM/schema tests, including token preservation, JSON edits and saved field-key compatibility.
- PHP syntax, JSON, whitespace and packaged-file integrity checks.
- Query tests use a disposable WordPress install with the official SQLite integration; ACF, the theme wrapper and Magic Card are substituted in the harness. Editor tests use a simulated DOM. Live ACF PRO/theme layouts and production MySQL still require staging smoke tests.
