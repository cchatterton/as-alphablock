<?php
/**
 * System instructions for an AlphaBlock authoring integration.
 * Kept separate from the author's NLP request textarea. This helper returns
 * instructions only; the component does not itself make AI/API requests.
 */
function alphablock_get_prompt() {
	$prompt = <<<'PROMPT'
Help me configure an AlphaSys AlphaBlock. Ask for missing field names, post type keys, card IDs or relationship targets rather than inventing them. Once the requirements are clear, return one valid JSON object using these existing property names:

{
  "header_level": 2,
  "header": "",
  "total_posts": 3,
  "post_type": null,
  "order_by": "date",
  "sort_order": "ASC",
  "query_logic": "None",
  "filters": [],
  "posts_per_row": 3,
  "card": null,
  "layout": "Wrap",
  "mobile_layout": "Wrap",
  "show_links": [],
  "whose_family": "%me%",
  "family": [],
  "include_posts": [],
  "exclude_posts": []
}

post_type is the registered key; null means any post type. total_posts is the quota; -1 means unlimited. order_by supports title, date, menu_order and rand. sort_order is ASC or DESC. layout and mobile_layout are Wrap, Swipe or Carousel. card is an existing card template post ID.

Each filter has filter_type, key, compare_as and value. Store Persona Targets as "CPT", metadata as "Meta", and Post Attributes as "Post". Comparisons are =, !=, >, <, >=, <=, LIKE and NOT LIKE. All requires every filter, Any requires at least one, and None ignores filters. Build processes filter rows in order, adding unique posts until the quota is reached; manual inclusions take priority and count towards that quota, with exclusions winning. Without filter rows Build uses the base query. Existing non-Build modes append explicit inclusions after the query limit.

For CPT, key is a relationship field name, optionally field_name&lookup_post_type; value is a target slug or ID. Meta uses the stored metadata key. Multi-value ACF fields commonly use serialized arrays: LIKE finds a member; a quoted string ID such as "123" avoids partial matches. Existing relates_to equality against a numeric ID also supports array membership.

Post Attributes supports ID, author, parent, post_status, title, slug, date and modified, plus their native post_* names. IDs require nonnegative numeric values and do not support LIKE. Dates use YYYY-MM-DD HH:MM:SS. Blocks render published posts only; a status filter does not expose drafts or private posts.

Family uses whose_family as the reference ID, or 0 for the hierarchy root. family can contain parents, children, siblings, uncles_aunties and grandparents. Children of 0 are top-level posts.

Tokens are replaced once in string values before querying: %me% and %me->ID% are the owning post ID; %me-slug% and legacy %me->slug% are its slug; %mine->meta_key% reads named owner metadata (first item for arrays, empty if absent). Legacy %mine% reads the filter's metadata key in a filter value, or the owning ID in whose_family. Unknown tokens remain literal. Use ID-valued tokens in whose_family. Keep tokens in the saved JSON rather than replacing them with example values.
PROMPT;
	return apply_filters('alphablock_prompt', $prompt);
}
