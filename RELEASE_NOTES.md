AlphaBlock v3.3.1 corrects the NLP request input introduced in v3.3.0.

- The NLP tab's **Your request** textarea starts empty. The example is placeholder text, not a saved value.
- System instructions remain separately in `prompt.php`; they are no longer used to pre-populate the request field.
- If v3.3.0 saved an exact copy of those generated instructions in the request field, the editor clears that exact value on load. Normal user requests are preserved; saving the block persists the correction.
- Existing `field_prompt` / `prompt` storage keys remain intact. Query, token and card output are unchanged.

### Installation

Replace the complete `blocks/alphablock/` folder with the folder in `as-alphablock-3.3.1.zip`. Sync the ACF field group from local JSON if needed to show the new label and placeholder. Reload the editor; save affected blocks if the old generated text was previously persisted. No live-site deployment or database-wide rewrite is performed.

### NLP integration status

The supplied component has no NLP-to-JSON request handler or AI API connection. This patch separates the author's input from the system instructions; typing a request alone does not yet generate JSON. An authoring integration must connect the two, resolve the selected card's actual ID and apply the returned configuration.

### Validation

95 WordPress/database assertions, including the 40 supplied-3.2.1 render comparisons, plus six editor/schema tests. PHP syntax, JSON and release ZIP integrity checks pass. Tests use disposable WordPress/SQLite and simulated editor fields; live ACF PRO/theme integration still requires staging checks.
