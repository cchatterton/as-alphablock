<?php
/**
 * File: AlphaBlock
 *
 * @subpackage Blocks
 * @version    3.3.3
 * @author     Chris Chatterton
 *
 * Release Notes:
 * 3.3.3 20261030: Add opt-in JSON debug flag for final query arguments.
 * 3.3.0 20261030: Extract tokens/args, add waterfall Build and Post Attributes.
 * 3.2.1 20261029: Replace %me% and %me->slug% throughout input values in one pass.
 * 3.2.0 20261025: Separate raw values, token replacement and processing.
 * 3.1.11 20261025: Give each token its own independent replacement block.
 * 3.1.10 20261025: Replace %me% with an ID and %mine% with owner meta before queries.
 * 3.1.9 20261025: Process resolved and literal filter values identically.
 * 3.1.8 20261025: Resolve %me% by filter context before building queries.
 * 3.1.7 20261023: Restore 3.1.4 behavior and document the template sections.
 * 3.1.4 20261022: Treat a null post_type (- Select -) as WordPress any for all queries.
 * 3.1.3 20261022: Render the posts selected by the owning FAQ's relates_to field, across post types.
 * 3.1.2 20261022 CC: Match relates_to relationship fields against the owning post ID for Meta %me% filters.
 * 3.1  20260828 CC: Added whose_family support for %me%, hierarchy root 0 and explicit post IDs.
 * 3.0  20260827 CC: Family queries always use the owning page's post type.
 * 2.9  20260827 CC: Preserve a null post_type so Family queries inherit the owning post type.
 * 2.8  20260827 CC: JSON is now the single source for the block configuration.
 * 2.7  20260825 CC: Resolve Help Guides wiki_id when rendering Wiki content internally
 * 2.6  20260825 CC: Resolve private-page context from authenticated wp-admin iframe requests
 * 2.5  20260825 CC: Resolve postId and postType from ACF and WordPress block context
 * 2.4  20260825 CC: Resolve the owning post consistently for post type and Family relationships
 * 2.3  20260825 CC: Resolve an empty post type from the current page before building any query
 * 2.2  20260825 CC: Implemented additive Family queries for parents, children, uncles/aunties, siblings and grandparents
 * 2.1  20251108 CC: Accept runtime attributes via $block['attrs']['data'] (overrides ACF fields)
 * 2.0  20250906 CC: Converted to ACF framework
 * 1.9  20250325_CC: Extended for includes as comma seperated ids or meta key & support for "Any" post type.
 * 1.8  20250312 CC: Added support for hierarchical content (parents, children and siblings) + Added Language Support with WPML
 * 1.7  20250110 CC: Extended %me% as %mine%
 * 1.6  20250108 CC: Added includes
 * 1.5  20241220 CC: Added Unique for Random
 * 1.4  20241213 CC: added suppot for .my-grid and block_classes()
 * 1.3  20241212 CC: added support for random sorting
 * 1.2  20241211 CC: added support for no content
 * 1.1  20241210 CC: added %me% support
 * 1.0  20241203 CC: Clone of Superblock v1.2
 */

/* ================================================================
 * 1. GET RAW VALUES
 * Read the JSON and runtime overrides, then identify the post containing
 * this block. No card query or rendering happens in this step.
 * ================================================================ */

/*
 * Load the complete block configuration from one ACF field.
 * This is the only get_field() call in the template.
 */
$raw_json = get_field('json');

/* Allow callers to replace the complete JSON payload at runtime. */
if (isset($block['attrs']['data']['json'])) {
	$raw_json = $block['attrs']['data']['json'];
}

$json_error   = '';
$decoded_json = [];

/* Accept either an already-decoded array or JSON text from the ACF field. */
if (is_array($raw_json)) {
	$decoded_json = $raw_json;
} elseif (is_string($raw_json) && trim($raw_json) !== '') {
	$decoded_json = json_decode($raw_json, true);

	if (!is_array($decoded_json)) {
		$json_error   = json_last_error_msg();
		$decoded_json = [];
	}
}

/*
 * Safe defaults are used for missing properties and invalid/empty JSON.
 * Values present in the JSON replace these defaults.
 */
$block_defaults = [
	'header_level'  => 2,
	'header'        => '',
	'total_posts'   => 3,
	'post_type'     => null,
	'order_by'      => 'date',
	'sort_order'    => 'ASC',
	'query_logic'   => 'None',
	'filters'       => [],
	'posts_per_row' => 3,
	'card'          => null,
	'layout'        => 'Wrap',
	'mobile_layout' => 'Wrap',
	'show_links'    => [],
	'whose_family'  => '%me%',
	'family'        => [],
	'include_posts' => [],
	'exclude_posts' => [],
	'debug'         => 0,
];

$block_values = array_replace(
	$block_defaults,
	$decoded_json
);

/*
 * Preserve the existing 2.1 individual runtime overrides. This does not
 * perform any additional ACF reads.
 */
$runtime_data = $block['attrs']['data'] ?? [];

if (is_array($runtime_data)) {
	foreach (array_keys($block_defaults) as $field_name) {
		if (array_key_exists($field_name, $runtime_data)) {
			$block_values[$field_name] =
				$runtime_data[$field_name];
		}
	}
}

global $post;

/*
 * Resolve the post that owns this block.
 *
 * ACF can render the template from the front end, block editor,
 * an iframe or an internal Wiki request. The global $post is
 * deliberately the final fallback rather than the source of truth.
 */
$alphablock_acf_context =
	isset($context) &&
	is_array($context)
		? $context
		: [];

$alphablock_wp_context =
	isset($wp_block) &&
	is_object($wp_block) &&
	isset($wp_block->context) &&
	is_array($wp_block->context)
		? $wp_block->context
		: [];

$alphablock_attribute_context =
	isset($block['context']) &&
	is_array($block['context'])
		? $block['context']
		: [];

$alphablock_admin_post_id = 0;

/* In authenticated editor requests, recover the edited post ID if needed. */
if (is_admin() && is_user_logged_in()) {
	$alphablock_validate_admin_post_id =
		static function ($value) {
			if (!is_scalar($value)) {
				return 0;
			}

			$value = sanitize_text_field(
				wp_unslash((string) $value)
			);

			if (
				!preg_match(
					'/(\d+)$/',
					$value,
					$matches
				)
			) {
				return 0;
			}

			$candidate_id = absint($matches[1]);

			if (
				!$candidate_id ||
				!get_post($candidate_id) ||
				(
					!current_user_can(
						'read_post',
						$candidate_id
					) &&
					!current_user_can(
						'edit_post',
						$candidate_id
					)
				)
			) {
				return 0;
			}

			return $candidate_id;
		};

	$alphablock_request_keys = [
		'post_id',
		'postId',
		'post',
		'page_id',
		'p',
		'wiki_id',
	];

	foreach (
		$alphablock_request_keys
		as $alphablock_request_key
	) {
		if (
			!isset(
				$_REQUEST[
					$alphablock_request_key
				]
			)
		) {
			continue;
		}

		$alphablock_admin_post_id =
			$alphablock_validate_admin_post_id(
				$_REQUEST[
					$alphablock_request_key
				]
			);

		if ($alphablock_admin_post_id) {
			break;
		}
	}

	if (!$alphablock_admin_post_id) {
		$alphablock_referer = wp_get_referer();

		$alphablock_referer_query =
			$alphablock_referer
				? wp_parse_url(
					$alphablock_referer,
					PHP_URL_QUERY
				)
				: '';

		if ($alphablock_referer_query) {
			$alphablock_referer_args = [];

			wp_parse_str(
				$alphablock_referer_query,
				$alphablock_referer_args
			);

			foreach (
				$alphablock_request_keys
				as $alphablock_request_key
			) {
				if (
					!isset(
						$alphablock_referer_args[
							$alphablock_request_key
						]
					)
				) {
					continue;
				}

				$alphablock_admin_post_id =
					$alphablock_validate_admin_post_id(
						$alphablock_referer_args[
							$alphablock_request_key
						]
					);

				if ($alphablock_admin_post_id) {
					break;
				}
			}
		}
	}
}

/* Prefer block/ACF context, then the request, queried object and global post. */
$alphablock_post_candidates = [
	isset($post_id)
		? absint($post_id)
		: 0,

	isset($alphablock_acf_context['postId'])
		? absint(
			$alphablock_acf_context['postId']
		)
		: 0,

	isset($alphablock_wp_context['postId'])
		? absint(
			$alphablock_wp_context['postId']
		)
		: 0,

	isset($alphablock_attribute_context['postId'])
		? absint(
			$alphablock_attribute_context['postId']
		)
		: 0,

	isset($alphablock_acf_context['hostPostId'])
		? absint(
			$alphablock_acf_context['hostPostId']
		)
		: 0,

	$alphablock_admin_post_id,

	absint(get_queried_object_id()),

	!empty($post->ID)
		? absint($post->ID)
		: 0,
];

$alphablock_post_id   = 0;
$alphablock_post_type = false;

foreach (
	array_unique(
		array_filter(
			$alphablock_post_candidates
		)
	)
	as $alphablock_post_candidate
) {
	$alphablock_candidate_type = get_post_type(
		$alphablock_post_candidate
	);

	if ($alphablock_candidate_type) {
		$alphablock_post_id =
			$alphablock_post_candidate;

		$alphablock_post_type =
			$alphablock_candidate_type;

		break;
	}
}

if (!$alphablock_post_type) {
	$alphablock_context_post_types = array_filter([
		$alphablock_acf_context['postType']
			?? '',

		$alphablock_wp_context['postType']
			?? '',

		$alphablock_attribute_context['postType']
			?? '',
	]);

	if (!empty($alphablock_context_post_types)) {
		$alphablock_post_type = sanitize_key(
			reset($alphablock_context_post_types)
		);
	}
}

if (!$alphablock_post_type) {
	$alphablock_queried_object =
		get_queried_object();

	if (
		is_object($alphablock_queried_object) &&
		!empty($alphablock_queried_object->ID) &&
		!empty(
			$alphablock_queried_object->post_type
		)
	) {
		$alphablock_post_id = absint(
			$alphablock_queried_object->ID
		);

		$alphablock_post_type =
			$alphablock_queried_object->post_type;
	}
}

/* ================================================================
 * 2. REPLACE TOKENS
 * Resolve all configured string values once, using the owning post context.
 * ================================================================ */
require_once __DIR__ . '/tokens.php';
$block_values = alphablock_replace_tokens($block_values, $alphablock_post_id);

/* ================================================================
 * 3. PROCESS
 * Normalize values, build the query and render cards. This step does not
 * check whether any value was typed directly or produced by a token.
 * ================================================================ */

/*
 * Normalize configuration types and limits once. Later sections can then
 * use these values without checking every possible editor input shape.
 */
$block_values['header_level'] = min(
	6,
	max(
		1,
		(int) $block_values['header_level']
	)
);

$block_values['header'] =
	(string) $block_values['header'];

$block_values['total_posts'] =
	(int) $block_values['total_posts'];

$block_values['post_type'] =
	is_scalar($block_values['post_type'])
		? sanitize_key(
			(string) $block_values['post_type']
		)
		: '';

$block_values['order_by'] =
	$block_values['order_by'] ?: 'date';

$block_values['sort_order'] =
	strtoupper(
		(string) $block_values['sort_order']
	) === 'DESC'
		? 'DESC'
		: 'ASC';

$block_values['query_logic'] =
	$block_values['query_logic'] ?: 'None';

$block_values['filters'] =
	is_array($block_values['filters'])
		? $block_values['filters']
		: [];

$block_values['family'] =
	is_array($block_values['family'])
		? $block_values['family']
		: [];

$block_values['show_links'] =
	is_array($block_values['show_links'])
		? $block_values['show_links']
		: [];

$whose_family = trim((string) $block_values['whose_family']);

if (preg_match('/^\d+$/', $whose_family)) {
	$block_values['whose_family'] =
		(int) $whose_family;
} else {
	$block_values['whose_family'] = $alphablock_post_id;
}

$block_values['include_posts'] = array_values(
	array_filter(
		array_map(
			'intval',
			(array) $block_values['include_posts']
		)
	)
);

$block_values['exclude_posts'] = array_values(
	array_filter(
		array_map(
			'intval',
			(array) $block_values['exclude_posts']
		)
	)
);

$block_values['posts_per_row'] = max(
	1,
	(int) $block_values['posts_per_row']
);

// Debug is opt-in through JSON (or a runtime override), never inferred as truthy.
$block_values['debug'] = in_array($block_values['debug'], [1, '1'], true) ? 1 : 0;

/* The selected Card post supplies the slug used by Magic_Card below. */
$card_id = (int) $block_values['card'];

$card_post =
	$card_id > 0
		? get_post($card_id)
		: null;

$card_slug =
	$card_post instanceof WP_Post
		? $card_post->post_name
		: '';

/* The editor preview shows a summary and stops before running the query. */
if ($is_preview) {
	$preview_card =
		$card_post instanceof WP_Post
			? get_the_title($card_post)
			: '';
	?>
	<div class="block-meta">
		<span>
			<strong>
				<span class="dashicons dashicons-block-default"></span>
				AlphaBlock
			</strong>
		</span>

		<span>
			Header:
			<?= esc_html(
				ucfirst(
					$block_values['header']
						?: 'Not defined'
				)
			); ?>
		</span>

		<span>
			Post type:
			<?= esc_html(
				ucfirst(
					(string) (
						$block_values['post_type']
							?: 'Not selected'
					)
				)
			); ?>
		</span>

		<span>
			Card:
			<?= esc_html(
				$preview_card
					?: 'Not selected'
			); ?>
		</span>

		<?php if ($json_error !== '') : ?>
			<span>
				JSON error:
				<?= esc_html($json_error); ?>
			</span>
		<?php endif; ?>
	</div>
	<?php

	return;
}

/* Give this block instance a stable wrapper ID and its theme classes. */
$block_id =
	isset($block['id'])
		? esc_attr($block['id'])
		: uniqid('block_');

$block_classes = block_classes();

/*
 * Build the section and optional heading now. The card list is inserted at
 * %%content%% after the query has produced its post IDs.
 */
ob_start();

echo "\n"
	. '<!-- alphablock: '
	. esc_html($block_values['header'])
	. ' '
	. esc_html($block_id)
	. ' -->'
	. "\n";

printf(
	'<div id="alphablock-%1$s" class="section-wrapper %2$s">'
	. "\n"
	. '  <div class="section-inner-wrapper">'
	. "\n",
	esc_attr($block_id),
	esc_attr($block_classes)
);

if ($block_values['header'] !== '') {
	printf(
		'<h%1$d>%2$s</h%1$d>',
		$block_values['header_level'],
		esc_html($block_values['header'])
	);
}

echo '<div class="card-wrapper">';
echo '%%content%%';
echo '</div>';
echo "  </div>\n";
echo "</div>\n";

echo "\n"
	. '<!-- /alphablock: '
	. esc_html($block_values['header'])
	. ' '
	. esc_html($block_id)
	. ' -->'
	. "\n";

$wrapper = ob_get_clean();

/* Query construction uses normalized values after token resolution. */
require_once __DIR__ . '/args.php';
$query_plan = alphablock_build_args($block_values);
$total_posts = $block_values['total_posts'];

if ($query_plan['mode'] === 'build') {
	$total_posts = $total_posts === -1 ? -1 : max(0, $total_posts);
	/* Manual inclusions have first priority and consume the waterfall quota. */
	$pids = [];
	if ($total_posts !== 0 && $block_values['include_posts']) {
		$manual_ids = array_values(array_diff($block_values['include_posts'], $block_values['exclude_posts']));
		if ($manual_ids) {
			$args = [
				'post__in' => $manual_ids,
				'post_type' => array_values(array_unique(array_filter(array_map('get_post_type', $manual_ids)))),
				'post_status' => 'publish',
				'posts_per_page' => $total_posts === -1 ? -1 : max(0, $total_posts),
				'orderby' => 'post__in', 'fields' => 'ids', 'suppress_filters' => false,
			];
			if ($block_values['debug'] === 1) {
				pre_dump($args);
			}
			$pids = get_posts($args);
		}
	}
	foreach ($query_plan['steps'] as $condition) {
		$gap = $total_posts === -1 ? -1 : max(0, $total_posts - count($pids));
		if ($gap === 0) {
			break;
		}
		$args = alphablock_build_step_args($query_plan['args'], $condition, $gap, $pids);
		if ($args === null) {
			continue;
		}
		if ($block_values['debug'] === 1) {
			pre_dump($args);
		}
		$posts = get_posts($args);
		foreach ($posts as $selected_post) {
			if (!in_array($selected_post->ID, $pids, true)) {
				$pids[] = $selected_post->ID;
			}
			if ($total_posts !== -1 && count($pids) >= max(0, $total_posts)) {
				break;
			}
		}
	}
} else {
	/* Preserve 3.2.1 sorting, limiting and additive inclusions in existing modes. */
	$args = $query_plan['args'];
	if ($block_values['debug'] === 1) {
		pre_dump($args);
	}
	$posts = $query_plan['empty'] ? [] : get_posts($args);
	if ($block_values['order_by'] === 'rand') {
		shuffle($posts);
	}
	$max_posts = $total_posts === -1 ? count($posts) : max(0, $total_posts);
	$pids = array_map(static function ($queried_post) {
		return $queried_post->ID;
	}, array_slice($posts, 0, $max_posts));
	if (!empty($block_values['include_posts'])) {
		$pids = array_values(array_unique(array_merge($pids, $block_values['include_posts'])));
	}
}

/* Convert desktop/mobile layout settings into CSS classes and row width. */
$per = $block_values['posts_per_row'];

$layout_lg = strtolower(
	trim(
		(string) (
			$block_values['layout']
				?: 'wrap'
		)
	)
);

$layout_sm = strtolower(
	trim(
		(string) (
			$block_values['mobile_layout']
				?: 'wrap'
		)
	)
);

if (
	$layout_lg !== 'wrap' ||
	$layout_sm !== 'wrap'
) {
	$per += 0.2;
}

$classes =
	'card-row card-layout-'
	. sanitize_html_class($layout_lg)
	. '-lg card-layout-'
	. sanitize_html_class($layout_sm)
	. '-sm';

$style =
	'style="--per-row:'
	. esc_attr($per)
	. ';"';

/* Render each selected post with the chosen Magic Card template. */
$one = '';

if ($card_slug !== '') {
	foreach ($pids as $pid) {
		$one .=
			'<li class="card-item">'
			. Magic_Card::get_card(
				$card_slug,
				$pid
			)
			. '</li>';
	}
}

$needs_carousel =
	$layout_sm === 'carousel' ||
	$layout_lg === 'carousel';

/* Carousel layouts repeat items so the theme can scroll continuously. */
$duplicates = '';

if (
	$needs_carousel &&
	$card_slug !== ''
) {
	for ($copy = 0; $copy < 2; $copy++) {
		foreach ($pids as $pid) {
			$duplicates .=
				'<li class="card-item card-dup">'
				. Magic_Card::get_card(
					$card_slug,
					$pid
				)
				. '</li>';
		}
	}
}

/* Replace the placeholder in the wrapper with the completed card list. */
$content =
	'<ul class="'
	. esc_attr($classes)
	. '" '
	. $style
	. '>'
	. $one
	. $duplicates
	. '</ul>';

echo preg_replace(
	'/%%content%%/',
	$content,
	$wrapper,
	1
);