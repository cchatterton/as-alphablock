<?php
/**
 * Run ONLY in a disposable WordPress installation:
 * wp eval-file /absolute/path/to/as-alphablock/tests/run-wordpress.php --use-include
 * Creates test posts/users in that installation. Uses real WP queries and DB;
 * substitutes only the external ACF field getter, theme helper and card renderer.
 */
if (!defined('ABSPATH') || !defined('WP_CLI')) {
	fwrite(STDERR, "Run this file through wp eval-file in a disposable WordPress install.\n");
	exit(1);
}
if (function_exists('get_field') || class_exists('Magic_Card')) {
	throw new RuntimeException('Use a disposable installation without ACF or Magic Card active.');
}
error_reporting(E_ALL & ~E_DEPRECATED);
set_error_handler(static function ($severity, $message, $file, $line) {
	if (error_reporting() & $severity) {
		throw new ErrorException($message, 0, $severity, $file, $line);
	}
	return false;
});
$root = dirname(__DIR__);
require_once $root . '/alphablock/tokens.php';
require_once $root . '/alphablock/args.php';
require_once $root . '/alphablock/block.php';
$GLOBALS['ab_checks'] = 0;
function ab_same($expected, $actual, $label) {
	$GLOBALS['ab_checks']++;
	if ($expected !== $actual) {
		throw new RuntimeException($label . "\nExpected: " . var_export($expected, true) . "\nActual: " . var_export($actual, true));
	}
}
if (!function_exists('get_field')) {
	function get_field($key) { return $GLOBALS['ab_raw_json']; }
}
function block_classes() { return 'existing-theme-class'; }
function pre_dump($value) {
	$GLOBALS['ab_dumps'][] = $value;
	echo '<pre class="test-debug">';
	var_dump($value);
	echo '</pre>';
}
if (!class_exists('Magic_Card')) {
class Magic_Card {
	public static function get_card($slug, $id) {
		if (get_post_status($id) !== 'publish') { return false; }
		$GLOBALS['ab_card_ids'][] = $id;
		return '<article data-card="' . esc_attr($slug) . '" data-post="' . (int) $id . '">' . esc_html(get_the_title($id)) . '</article>';
	}
}
}
function ab_post($title, $type = 'stories', $parent = 0, $meta = [], $author = 1, $status = 'publish') {
	$id = wp_insert_post([
		'post_title' => $title, 'post_type' => $type, 'post_status' => $status,
		'post_parent' => $parent, 'post_author' => $author,
		'post_date' => '2026-10-01 10:00:00', 'post_content' => 'Fixture content',
	], true);
	if (is_wp_error($id)) { throw new RuntimeException($id->get_error_message()); }
	update_post_meta($id, '_alphablock_test_fixture', 1);
	foreach ($meta as $key => $value) { update_post_meta($id, $key, $value); }
	return $id;
}
function ab_filter($key, $value, $compare = '=', $type = 'Meta') {
	return ['filter_type' => $type, 'key' => $key, 'compare_as' => $compare, 'value' => $value];
}
function ab_render($file, array $values, array $options = []) {
	$GLOBALS['ab_raw_json'] = $options['raw_json'] ?? wp_json_encode($values);
	$GLOBALS['ab_card_ids'] = [];
	$GLOBALS['ab_dumps'] = [];
	$block = ['id' => 'compatibility-test', 'attrs' => ['data' => $options['runtime'] ?? []]];
	$is_preview = $options['preview'] ?? false;
	$post_id = $options['owner'] ?? $GLOBALS['ab_owner'];
	$context = $options['context'] ?? [];
	$wp_block = isset($options['wp_context']) ? (object) ['context' => $options['wp_context']] : null;
	if (isset($options['block_context'])) { $block['context'] = $options['block_context']; }
	global $post;
	$post = get_post($GLOBALS['ab_unrelated']);
	$_REQUEST = [];
	ob_start();
	try { include $file; } catch (Throwable $error) { ob_end_clean(); throw $error; }
	return ['html' => ob_get_clean(), 'ids' => $GLOBALS['ab_card_ids'], 'dumps' => $GLOBALS['ab_dumps']];
}
register_post_type('stories', ['public' => true, 'hierarchical' => true]);
register_post_type('story', ['public' => true]);
register_post_type('faq', ['public' => true]);
register_post_type('card', ['public' => false]);
register_post_type('list_test', ['public' => true]);
register_post_type('internal_content', ['public' => false, 'exclude_from_search' => true]);
foreach (get_posts(['post_type' => array_keys(get_post_types()), 'post_status' => 'any', 'posts_per_page' => -1, 'fields' => 'ids', 'meta_key' => '_alphablock_test_fixture']) as $previous_fixture) {
	wp_delete_post($previous_fixture, true);
}

$author2 = wp_insert_user(['user_login' => 'ab_author_' . wp_generate_password(8, false), 'user_pass' => wp_generate_password()]);
$grandparent = ab_post('Hierarchy grandparent', 'page');
$parent = ab_post('Hierarchy parent', 'page', $grandparent);
$owner = ab_post('Owning page', 'page', $parent);
$sibling = ab_post('Hierarchy sibling', 'page', $parent);
$child = ab_post('Hierarchy child', 'page', $owner);
$aunt = ab_post('Hierarchy aunt', 'page', $grandparent);
$unrelated = ab_post('Unrelated global', 'page');
$GLOBALS['ab_owner'] = $owner;
$GLOBALS['ab_unrelated'] = $unrelated;
$card = ab_post('Test card', 'card');
$a = ab_post('A priority', 'stories', 0, ['tier' => 'first']);
$b = ab_post('B priority', 'stories', 0, ['tier' => 'first', 'second' => 'yes']);
$c = ab_post('C fallback', 'stories', $owner, ['tier' => 'second', 'second' => 'yes']);
$d = ab_post('D fallback', 'stories', $owner, ['tier' => 'second', 'second' => 'yes']);
$e = ab_post('E attribute only', 'stories', 0, [], $author2);
$draft = ab_post('Hidden draft', 'stories', 0, ['tier' => 'first'], 1, 'draft');
$private = ab_post('Hidden private', 'stories', 0, [], 1, 'private');
$old_story = ab_post('Legacy singular key', 'story');
$internal = ab_post('Explicit internal content', 'internal_content');
$faq_string = ab_post('FAQ strings', 'faq', 0, ['relates_to' => [(string) $owner]]);
$faq_integer = ab_post('FAQ integers', 'faq', 0, ['relates_to' => [$owner]]);
$faq_scalar = ab_post('FAQ scalar', 'faq', 0, ['relates_to' => $owner]);
$faq_wrong = ab_post('FAQ partial ID', 'faq', 0, ['relates_to' => [(string) ($owner . '4')]]);
update_post_meta($owner, 'topic', [$parent, $grandparent]);
update_post_meta($owner, 'related_parent', $parent);
update_post_meta($owner, 'literal_token', '%me%');
update_post_meta($owner, 'zero', 0);

$base = [
	'header_level' => 2, 'header' => 'Existing heading', 'total_posts' => 3,
	'post_type' => 'stories', 'order_by' => 'title', 'sort_order' => 'ASC',
	'query_logic' => 'None', 'filters' => [], 'posts_per_row' => 3, 'card' => $card,
	'layout' => 'Wrap', 'mobile_layout' => 'Wrap', 'show_links' => [],
	'whose_family' => '%me%', 'family' => [], 'include_posts' => [], 'exclude_posts' => [],
];
$old = $root . '/tests/fixtures/template-3.2.1.php';
$new = $root . '/alphablock/template.php';
$cases = [];
$cases['defaults'] = [$base, []];
foreach (['None', 'All', 'Any', 'Family'] as $mode) {
	$cases['empty ' . $mode] = [array_replace($base, ['query_logic' => $mode]), []];
}
foreach (['All', 'Any'] as $mode) {
	$cases[$mode . ' meta'] = [array_replace($base, ['query_logic' => $mode, 'filters' => [ab_filter('tier', 'first'), ab_filter('second', 'yes')]]), []];
	$cases[$mode . ' relates_to'] = [array_replace($base, ['post_type' => 'faq', 'total_posts' => -1, 'query_logic' => $mode, 'filters' => [ab_filter('relates_to', '%me%')]]), []];
	$cases[$mode . ' unmatched CPT'] = [array_replace($base, ['query_logic' => $mode, 'filters' => [ab_filter('relates_to&page', 'nonexistent', 'LIKE', 'CPT')]]), []];
	$cases[$mode . ' CPT slug'] = [array_replace($base, ['post_type' => 'faq', 'query_logic' => $mode, 'filters' => [ab_filter('relates_to&page', '%me->slug%', 'LIKE', 'CPT')]]), []];
	$cases[$mode . ' CPT ID'] = [array_replace($base, ['post_type' => 'faq', 'query_logic' => $mode, 'filters' => [ab_filter('relates_to&page', '%me%', 'LIKE', 'CPT')]]), []];
	$cases[$mode . ' legacy mine'] = [array_replace($base, ['query_logic' => $mode, 'filters' => [ab_filter('topic&page', '%mine%', '=', 'CPT')]]), []];
}
foreach (['parents', 'children', 'siblings', 'uncles_aunties', 'grandparents'] as $family) {
	$cases['family ' . $family] = [array_replace($base, ['post_type' => 'page', 'total_posts' => -1, 'query_logic' => 'Family', 'family' => [$family]]), []];
}
$cases['family additive'] = [array_replace($base, ['post_type' => null, 'total_posts' => -1, 'query_logic' => 'Family', 'family' => ['parents', 'children', 'siblings', 'grandparents', 'uncles_aunties']]), []];
$cases['family legacy mine'] = [array_replace($base, ['post_type' => 'page', 'query_logic' => 'Family', 'family' => ['parents'], 'whose_family' => '%mine%']), []];
$cases['family root'] = [array_replace($base, ['post_type' => 'page', 'query_logic' => 'Family', 'family' => ['children'], 'whose_family' => 0, 'exclude_posts' => [$grandparent]]), []];
$cases['null means any'] = [array_replace($base, ['post_type' => null, 'total_posts' => -1]), []];
$cases['legacy singular type'] = [array_replace($base, ['post_type' => 'story']), []];
$cases['include after quota'] = [array_replace($base, ['total_posts' => 1, 'include_posts' => [$d, $a, $d]]), []];
$cases['include beats exclusion legacy'] = [array_replace($base, ['total_posts' => 1, 'include_posts' => [$b], 'exclude_posts' => [$b]]), []];
$cases['zero legacy'] = [array_replace($base, ['total_posts' => 0]), []];
$cases['header nested tokens'] = [array_replace($base, ['header' => 'Owner %me% at %me->slug%']), []];
$cases['editor preview'] = [$base, ['preview' => true]];
$cases['runtime override'] = [$base, ['runtime' => ['total_posts' => 1, 'header' => 'Override %me%']]];
$cases['runtime entire JSON'] = [$base, ['runtime' => ['json' => ['post_type' => 'stories', 'card' => $card, 'total_posts' => 2]]]];
$cases['invalid JSON'] = [$base, ['raw_json' => '{broken', 'preview' => true]];
$cases['malformed filters'] = [array_replace($base, ['query_logic' => 'All', 'filters' => [false, [], ['key' => []]]]), []];
$cases['context over global'] = [array_replace($base, ['header' => '%me%']), ['owner' => 0, 'context' => ['postId' => $owner]]];
$cases['WP block context'] = [array_replace($base, ['header' => '%me%']), ['owner' => 0, 'wp_context' => ['postId' => $owner]]];
$cases['attribute context'] = [array_replace($base, ['header' => '%me%']), ['owner' => 0, 'block_context' => ['postId' => $owner]]];
$cases['carousel markup'] = [array_replace($base, ['layout' => 'Carousel', 'mobile_layout' => 'Swipe', 'total_posts' => 2]), []];
foreach ($cases as $name => [$values, $options]) {
	ab_same(ab_render($old, $values, $options), ab_render($new, $values, $options), '3.2.1 compatibility: ' . $name);
}
foreach ([1, 2, 3, 4, 5, 6] as $level) {
	$rendered = ab_render($new, array_replace($base, ['header_level' => $level]));
	ab_same(true, str_contains($rendered['html'], '<h' . $level . '>Existing heading</h' . $level . '>'), 'numeric header level ' . $level);
}
foreach (['span', 'p'] as $tag) {
	$rendered = ab_render($new, array_replace($base, ['header_level' => $tag, 'header_class' => 'feature-heading large']));
	ab_same(true, str_contains($rendered['html'], '<' . $tag . ' class="feature-heading large">Existing heading</' . $tag . '>'), 'header tag and classes ' . $tag);
}
$safe_header = ab_render($new, array_replace($base, ['header_level' => 'script', 'header_class' => 'feature" onclick="alert(1)']));
ab_same(true, str_contains($safe_header['html'], '<h1 class="feature onclickalert1">Existing heading</h1>'), 'invalid tag and classes are safe');
// Make the database random candidate order stable, then verify the PHP shuffle.
$stable_rand = static function ($order) { return str_replace('RAND()', 'ID ASC', $order); };
add_filter('posts_orderby', $stable_rand);
$values = array_replace($base, ['order_by' => 'rand']);
mt_srand(123); $expected = ab_render($old, $values);
mt_srand(123); ab_same($expected, ab_render($new, $values), 'legacy random rendering');
remove_filter('posts_orderby', $stable_rand);

// Tokens: exact aliases, nested text, unknown names, one pass, metadata shapes.
$resolved = alphablock_replace_tokens([
	'header' => '%me%|%me->ID%|%me-slug%|%me->slug%|%unknown%',
	'nested' => ['value' => '10% off %mine->related_parent%', 'id' => 12, 'null' => null],
	'first' => '%mine->topic%', 'missing' => '%mine->absent%', 'zero' => '%mine->zero%',
	'no_cascade' => '%mine->literal_token%',
	'filters' => [ab_filter('topic&page', '%mine%')], 'whose_family' => '%mine%',
], $owner);
$slug = get_post_field('post_name', $owner);
ab_same("$owner|$owner|$slug|$slug|%unknown%", $resolved['header'], 'token aliases');
ab_same(['value' => '10% off ' . $parent, 'id' => 12, 'null' => null], $resolved['nested'], 'nested values and types');
ab_same((string) $parent, $resolved['first'], 'first metadata item');
ab_same('', $resolved['missing'], 'missing metadata');
ab_same('0', $resolved['zero'], 'zero metadata');
ab_same('%me%', $resolved['no_cascade'], 'no recursive token expansion');
ab_same((string) $parent, $resolved['filters'][0]['value'], 'legacy contextual metadata');
ab_same((string) $owner, $resolved['whose_family'], 'legacy family token');
$custom_tokens = static function ($tokens, $id) { $tokens['%abc%'] = 'custom ' . $id; return $tokens; };
add_filter('alphablock_tokens', $custom_tokens, 10, 2);
ab_same(['x' => 'custom ' . $owner], alphablock_replace_tokens(['x' => '%abc%'], $owner), 'extensible token map');
remove_filter('alphablock_tokens', $custom_tokens);
ab_same(['x' => '0||'], alphablock_replace_tokens(['x' => '%me%|%me-slug%|%mine->topic%'], 0), 'no global fallback for missing owner');
foreach (['%me%', '%me->ID%', '%mine->related_parent%'] as $token) {
	$values = array_replace($base, ['post_type' => 'page', 'query_logic' => 'Family', 'family' => ['children'], 'whose_family' => $token]);
	$expected_id = $token === '%mine->related_parent%' ? $parent : $owner;
	ab_same(ab_render($new, array_replace($values, ['whose_family' => $expected_id])), ab_render($new, $values), 'family token ' . $token);
}

// Waterfall integration: real queries, priority, gaps, duplicates and stop condition.
$build = array_replace($base, ['query_logic' => 'Build', 'total_posts' => 4, 'filters' => [
	ab_filter('tier', 'first'), ab_filter('second', 'yes'), ab_filter('relates_to&page', 'unused-target', 'LIKE', 'CPT'),
]]);
$queries = [];
$track = static function ($sql) use (&$queries) { $queries[] = $sql; return $sql; };
add_filter('posts_request', $track);
ab_same([$a, $b, $c, $d], ab_render($new, $build)['ids'], 'Build fills gap with unique posts');
remove_filter('posts_request', $track);
ab_same(false, str_contains(implode("\n", $queries), 'unused-target'), 'Build stops before later CPT lookup');
ab_same([$a, $b], ab_render($new, array_replace($build, ['total_posts' => 2]))['ids'], 'Build stops on first quota');
ab_same([$e, $a, $c, $d], ab_render($new, array_replace($build, ['include_posts' => [$e, $e, $b], 'exclude_posts' => [$b]]))['ids'], 'Build manual quota and exclusion precedence');
ab_same([$e], ab_render($new, array_replace($build, ['total_posts' => 1, 'include_posts' => [$e, $a]]))['ids'], 'manual inclusion order and cap');
ab_same([$internal], ab_render($new, array_replace($build, ['total_posts' => 1, 'include_posts' => [$internal]]))['ids'], 'explicit includes can select a type excluded from automatic any queries');
ab_same([], ab_render($new, array_replace($build, ['total_posts' => 0, 'include_posts' => [$e]]))['ids'], 'Build zero quota');
ab_same([], ab_render($new, array_replace($build, ['total_posts' => -2, 'include_posts' => [$e]]))['ids'], 'Build invalid negative quota');
ab_same([$a, $b, $c, $d], ab_render($new, array_replace($build, ['total_posts' => -1]))['ids'], 'Build unlimited');
ab_same([$a, $b, $c, $d], ab_render($new, array_replace($build, ['total_posts' => 9]))['ids'], 'Build shortfall');
ab_same([$a, $b, $c, $d], ab_render($new, array_replace($build, ['filters' => []]))['ids'], 'Build no-filter fallback');
ab_same([], ab_render($new, array_replace($build, ['filters' => [null, [], false, ['key' => []]]]))['ids'], 'Build malformed rows cannot broaden query');
ab_same([$a, $b], ab_render($new, array_replace($build, ['filters' => [ab_filter('ID', $b, '<=', 'Post')]]))['ids'], 'Build supports post attributes');
add_filter('posts_orderby', $stable_rand);
$random_build = ab_render($new, array_replace($build, ['order_by' => 'rand', 'total_posts' => 3]))['ids'];
remove_filter('posts_orderby', $stable_rand);
ab_same([$a, $b, $c], $random_build, 'Build keeps filter priority with random ordering');

// Attribute operators, mixed Any/All, published visibility, safe SQL and scoping.
$attribute_cases = [
	'author' => [ab_filter('author', $author2, '=', 'Post'), [$e]],
	'parent' => [ab_filter('parent', $owner, '=', 'Post'), [$c, $d]],
	'ID' => [ab_filter('ID', $b, '=', 'Post'), [$b]],
	'title contains' => [ab_filter('title', 'fallback', 'LIKE', 'Post'), [$c, $d]],
	'title not contains' => [ab_filter('title', 'fallback', 'NOT LIKE', 'Post'), [$a, $b, $e]],
	'title exact' => [ab_filter('title', 'A priority', '=', 'Post'), [$a]],
	'not equal' => [ab_filter('ID', $b, '!=', 'Post'), [$a, $c, $d, $e]],
	'greater' => [ab_filter('ID', $d, '>', 'Post'), [$e]],
	'greater equal' => [ab_filter('ID', $d, '>=', 'Post'), [$d, $e]],
	'less' => [ab_filter('ID', $b, '<', 'Post'), [$a]],
	'date' => [ab_filter('date', '2026-09-30 00:00:00', '>', 'Post'), [$a, $b, $c, $d, $e]],
	'published' => [ab_filter('post_status', 'publish', '=', 'Post'), [$a, $b, $c, $d, $e]],
	'draft is not exposed' => [ab_filter('post_status', 'draft', '=', 'Post'), []],
	'unknown property' => [ab_filter('unknown_column', 1, '=', 'Post'), []],
	'invalid operator' => [ab_filter('title', 'anything', 'OR 1=1', 'Post'), []],
	'invalid numeric value' => [ab_filter('author', 'abc', '=', 'Post'), []],
	'unsupported numeric LIKE' => [ab_filter('ID', '1', 'LIKE', 'Post'), []],
	'quoted SQL value' => [ab_filter('title', "x' OR 1=1 --", '=', 'Post'), []],
];
foreach ($attribute_cases as $name => [$filter, $expected_ids]) {
	ab_same($expected_ids, ab_render($new, array_replace($base, ['total_posts' => -1, 'query_logic' => 'All', 'filters' => [$filter]]))['ids'], 'Post Attributes: ' . $name);
}
$mix = [ab_filter('tier', 'first'), ab_filter('author', $author2, '=', 'Post')];
ab_same([$a, $b, $e], ab_render($new, array_replace($base, ['total_posts' => -1, 'query_logic' => 'Any', 'filters' => $mix]))['ids'], 'Any includes attribute-only posts with no matching metadata');
ab_same([], ab_render($new, array_replace($base, ['query_logic' => 'All', 'filters' => $mix]))['ids'], 'All intersects attributes and metadata');
$mix = [ab_filter('second', 'yes'), ab_filter('parent', $owner, '=', 'Post')];
ab_same([$c, $d], ab_render($new, array_replace($base, ['query_logic' => 'All', 'filters' => $mix]))['ids'], 'All matches both attribute and metadata');
ab_same([$a, $b, $c, $d, $e], get_posts(['post_type' => 'stories', 'post_status' => 'publish', 'posts_per_page' => -1, 'orderby' => 'title', 'order' => 'ASC', 'fields' => 'ids', 'suppress_filters' => false]), 'unrelated queries unaffected');
ab_same('Saved custom prompt', apply_filters('acf/load_value/key=field_prompt', 'Saved custom prompt'), 'saved prompts preserved');
ab_same('', apply_filters('acf/load_value/key=field_prompt', ''), 'empty NLP input stays empty');
ab_same('', apply_filters('acf/load_value/key=field_prompt', null), 'unset NLP input stays empty');
ab_same('', apply_filters('acf/load_value/key=field_prompt', alphablock_get_prompt()), 'remove exact system instructions accidentally saved by 3.3.0');
$request = 'Get all posts with state=nsw and the news card; Carousel desktop and Swipe mobile.';
ab_same($request, apply_filters('acf/load_value/key=field_prompt', $request), 'preserve actual NLP request');
// Debug prints only the final arguments, using the theme-provided function.
$without_debug = ab_render($new, $base);
$debug_render = ab_render($new, array_replace($base, ['debug' => 1]));
ab_same([], $without_debug['dumps'], 'debug is off by default');
ab_same([], ab_render($new, array_replace($base, ['debug' => 0]))['dumps'], 'explicit debug zero is off');
ab_same([], ab_render($new, array_replace($base, ['debug' => true]))['dumps'], 'truthy values do not enable debug');
ab_same(1, count($debug_render['dumps']), 'debug dumps one final main query');
ab_same($without_debug['ids'], $debug_render['ids'], 'debug does not change selected cards');
ab_same(true, str_contains($debug_render['html'], '<pre class="test-debug">'), 'theme dump is output on the page');
ab_same('stories', $debug_render['dumps'][0]['post_type'], 'debug shows final post type');
ab_same(3, $debug_render['dumps'][0]['posts_per_page'], 'debug shows final limit');
ab_same(1, count(ab_render($new, array_replace($base, ['debug' => '1']))['dumps']), 'string one enables debug');
ab_same(1, count(ab_render($new, $base, ['runtime' => ['debug' => 1]])['dumps']), 'runtime debug override');
ab_same([], ab_render($new, array_replace($base, ['debug' => 1]), ['preview' => true])['dumps'], 'editor preview runs no final query or dump');
$debug_build = ab_render($new, array_replace($build, ['debug' => 1]));
ab_same(2, count($debug_build['dumps']), 'Build dumps each executed step, not unused CPT lookups');
ab_same(4, $debug_build['dumps'][0]['posts_per_page'], 'first Build dump shows quota');
ab_same(2, $debug_build['dumps'][1]['posts_per_page'], 'second Build dump shows remaining gap');
ab_same([$a, $b], $debug_build['dumps'][1]['post__not_in'], 'later Build dump contains prior IDs');
$debug_manual = ab_render($new, array_replace($build, ['debug' => 1, 'include_posts' => [$e]]));
ab_same(3, count($debug_manual['dumps']), 'Build dumps manual selection and both executed filter queries');
ab_same([$e], $debug_manual['dumps'][0]['post__in'], 'manual dump shows actual inclusion args');
$debug_relation = ab_render($new, array_replace($base, ['debug' => 1, 'post_type' => 'faq', 'query_logic' => 'All', 'filters' => [ab_filter('relates_to', '%me%')]]));
ab_same('"' . $owner . '"', $debug_relation['dumps'][0]['meta_query'][0][0]['value'], 'dump shows resolved tokens and final relationship args');
// -1 means every selected post fits in one row, before carousel copies.
foreach ([['Wrap', 'Wrap'], ['Swipe', 'Wrap'], ['Carousel', 'Swipe'], ['Wrap', 'Carousel']] as [$desktop, $mobile]) {
	$config = array_replace($base, ['posts_per_row' => -1, 'layout' => $desktop, 'mobile_layout' => $mobile]);
	$rendered = ab_render($new, $config);
	ab_same(true, str_contains($rendered['html'], 'style="--per-row:3;"'), 'all-in-one-row width for ' . $desktop . '/' . $mobile);
	foreach ([1, 3, 12, 0, -2] as $fixed) {
		$fixed_config = array_replace($config, ['posts_per_row' => $fixed]);
		ab_same(ab_render($old, $fixed_config), ab_render($new, $fixed_config), 'existing row-width behaviour ' . $fixed . ' for ' . $desktop . '/' . $mobile);
	}
}
$row_config = array_replace($base, ['posts_per_row' => -1]);
ab_same(true, str_contains(ab_render($new, array_replace($row_config, ['total_posts' => -1]))['html'], 'style="--per-row:5;"'), 'row size uses actual selected count');
ab_same(true, str_contains(ab_render($new, array_replace($row_config, ['total_posts' => 1]))['html'], 'style="--per-row:1;"'), 'single selected item');
ab_same(true, str_contains(ab_render($new, array_replace($row_config, ['query_logic' => 'All', 'filters' => [ab_filter('tier', 'missing')]]))['html'], 'style="--per-row:1;"'), 'empty result has a safe row width');
ab_same(true, str_contains(ab_render($new, array_replace($row_config, ['include_posts' => [$e]]))['html'], 'style="--per-row:4;"'), 'row count includes appended manual posts');
ab_same(true, str_contains(ab_render($new, array_replace($build, ['posts_per_row' => -1]))['html'], 'style="--per-row:4;"'), 'Build row count uses its final quota result');
// Current-release compatibility: explicit/missing Card must match 3.3.5 exactly.
$card_baseline = $root . '/tests/fixtures/template-3.3.5.php';
// Only the snapshot's two relative include paths are adapted. Hash-check the
// unchanged shared helpers so a future refactor cannot silently alter this baseline.
foreach (json_decode(file_get_contents($root . '/tests/fixtures/helpers-3.3.5.json'), true) as $helper => $hash) {
	ab_same($hash, hash_file('sha256', $root . '/alphablock/' . $helper), 'unchanged 3.3.5 helper: ' . $helper);
}

foreach ($cases as $name => [$values, $options]) {
	ab_same(ab_render($card_baseline, $values, $options), ab_render($new, $values, $options), '3.3.5 missing-mode compatibility: ' . $name);
	ab_same(ab_render($card_baseline, $values, $options), ab_render($new, array_replace($values, ['show_as' => 'card', 'list_ul_class' => 'must-not-apply', 'list_icon' => 'fa-solid fa-check']), $options), '3.3.5 explicit Card compatibility: ' . $name);
}
foreach ([null, '', false, 0, [], 'unknown'] as $invalid_mode) {
	ab_same(ab_render($card_baseline, $base), ab_render($new, array_replace($base, ['show_as' => $invalid_mode])), 'invalid mode falls back to Card');
}
$named_list_card = ab_post('List', 'card');
$named_card_config = array_replace($base, ['card' => $named_list_card]);
ab_same(ab_render($card_baseline, $named_card_config), ab_render($new, $named_card_config), 'a card template named List stays a card');

function ab_list_nodes($html) {
	$document = new DOMDocument();
	@$document->loadHTML('<?xml encoding="utf-8" ?>' . $html);
	$xpath = new DOMXPath($document);
	$list = $xpath->query('//div[@class="list-wrapper"]/ul')->item(0);
	if (!$list) { throw new RuntimeException('Expected a list-wrapper containing a UL'); }
	return [$list, $xpath];
}
function ab_list_urls($html) {
	[$list] = ab_list_nodes($html);
	$urls = [];
	foreach ($list->getElementsByTagName('a') as $link) { $urls[] = $link->getAttribute('href'); }
	return $urls;
}
$list_config = array_replace($base, ['show_as' => 'list', 'card' => null]);
$list_render = ab_render($new, $list_config);
[$list, $xpath] = ab_list_nodes($list_render['html']);
ab_same(array_map('get_permalink', [$a, $b, $c]), ab_list_urls($list_render['html']), 'List uses selected posts and their permalinks');
ab_same([], $list_render['ids'], 'List never invokes Magic Card');
ab_same(false, $list->hasAttribute('class'), 'optional UL class is omitted');
ab_same(false, $list->hasAttribute('style'), 'no icon retains normal list bullets');
ab_same(false, $list->firstChild->hasAttribute('class'), 'optional LI class is omitted');
ab_same(false, $list->getElementsByTagName('a')->item(0)->hasAttribute('class'), 'optional A class is omitted');
ab_same(get_the_title($a), $list->getElementsByTagName('a')->item(0)->textContent, 'List link text is the post title');
ab_same(0, $xpath->query('//i')->length, 'no empty icon element');
ab_same(false, str_contains($list_render['html'], 'card-layout-'), 'List omits card layout classes');
ab_same(false, str_contains($list_render['html'], 'card-wrapper'), 'List avoids card wrapper styles');
ab_same(false, str_contains($list_render['html'], '--per-row'), 'List ignores card row widths');
ab_same(true, str_contains($list_render['html'], '<h2>Existing heading</h2>'), 'List retains the section heading');
$with_classes = array_replace($list_config, ['list_ul_class' => 'links extra', 'list_li_class' => 'link-item', 'list_a_class' => 'link-text', 'list_icon' => 'fa-solid fa-chevron-right']);
[$list, $xpath] = ab_list_nodes(ab_render($new, $with_classes)['html']);
ab_same('links extra', $list->getAttribute('class'), 'UL classes');
ab_same('link-item', $list->firstChild->getAttribute('class'), 'LI classes');
ab_same('link-text', $list->getElementsByTagName('a')->item(0)->getAttribute('class'), 'A classes');
ab_same('list-style:none;', $list->getAttribute('style'), 'icon replaces the native bullet');
ab_same(3, $list->getElementsByTagName('i')->length, 'one icon per item');
ab_same('fa-solid fa-chevron-right', $list->getElementsByTagName('i')->item(0)->getAttribute('class'), 'full Font Awesome classes');
ab_same('true', $list->getElementsByTagName('i')->item(0)->getAttribute('aria-hidden'), 'decorative icon hidden from assistive technology');
$carousel_list = array_replace($with_classes, ['layout' => 'Carousel', 'mobile_layout' => 'Carousel']);
[$list] = ab_list_nodes(ab_render($new, $carousel_list)['html']);
ab_same(3, $list->getElementsByTagName('li')->length, 'List ignores carousel duplicates');
ab_same(false, str_contains(ab_render($new, $carousel_list)['html'], 'card-dup'), 'List has no carousel markup');
ab_same(array_map('get_permalink', [$a, $b, $c, $d]), ab_list_urls(ab_render($new, array_replace($build, ['show_as' => 'list']))['html']), 'List shares Build selection and order');
ab_same(array_map('get_permalink', [$a, $b, $c, $e]), ab_list_urls(ab_render($new, array_replace($list_config, ['include_posts' => [$e]]))['html']), 'List shares explicit inclusions');
ab_same(array_map('get_permalink', [$a, $c, $d]), ab_list_urls(ab_render($new, array_replace($list_config, ['exclude_posts' => [$b]]))['html']), 'List shares exclusions');
ab_same([], ab_list_urls(ab_render($new, array_replace($list_config, ['total_posts' => 0, 'include_posts' => [$draft, $private, 99999999]]))['html']), 'List never exposes unpublished or missing manual posts');
ab_same([], ab_list_urls(ab_render($new, array_replace($list_config, ['query_logic' => 'All', 'filters' => [ab_filter('tier', 'missing')]]))['html']), 'List handles empty results');
ab_same(1, count(ab_render($new, array_replace($list_config, ['debug' => 1]))['dumps']), 'List retains debug query output');
$preview_list = ab_render($new, $list_config, ['preview' => true]);
ab_same([], $preview_list['ids'], 'List preview does not render cards');
ab_same(true, str_contains($preview_list['html'], 'Show as:'), 'List preview identifies its renderer');
ab_same(true, str_contains($preview_list['html'], 'List'), 'List preview label');
ab_same(false, str_contains($preview_list['html'], '<ul'), 'List preview does not query/render the list');
$token_classes = ab_render($new, array_replace($list_config, ['list_ul_class' => 'owner-%me%', 'list_icon' => '%unknown%']));
[$list] = ab_list_nodes($token_classes['html']);
ab_same('owner-' . $owner, $list->getAttribute('class'), 'List classes receive token replacement');
ab_same('%unknown%', $list->getElementsByTagName('i')->item(0)->getAttribute('class'), 'unknown List tokens remain literal');
$special = ab_post('Dollar $1 & <b>Title</b>', 'list_test');
$unsafe = array_replace($list_config, ['total_posts' => 0, 'include_posts' => [$special], 'list_ul_class' => 'links" onclick="evil()', 'list_li_class' => '<script>bad()</script>', 'list_a_class' => 'link" onmouseover="evil()', 'list_icon' => 'fa-check" onclick="evil()']);
[$list, $xpath] = ab_list_nodes(ab_render($new, $unsafe)['html']);
ab_same(false, $list->hasAttribute('onclick'), 'UL classes cannot inject attributes');
ab_same(false, $list->getElementsByTagName('a')->item(0)->hasAttribute('onmouseover'), 'A classes cannot inject attributes');
ab_same(false, $list->getElementsByTagName('i')->item(0)->hasAttribute('onclick'), 'icon classes cannot inject attributes');
ab_same(0, $xpath->query('//script')->length, 'List cannot inject HTML');
ab_same(html_entity_decode(get_the_title($special), ENT_QUOTES | ENT_HTML5, 'UTF-8'), $list->getElementsByTagName('a')->item(0)->textContent, 'escaped title preserves ampersands, markup and literal dollar signs');
ab_same(0, $list->getElementsByTagName('b')->length, 'title markup is rendered as text');
ab_same(get_permalink($special), $list->getElementsByTagName('a')->item(0)->getAttribute('href'), 'special title still uses its real permalink');
ab_same('', apply_filters('acf/load_value/key=field_prompt', alphablock_get_legacy_prompt()), 'old generated prompt is still cleared after system instructions evolve');
ab_same('', apply_filters('acf/load_value/key=field_prompt', alphablock_get_previous_prompt()), '3.4.0 generated prompt is still cleared');
ab_same(true, str_contains(alphablock_get_prompt(), '"show_as": "card"'), 'system prompt documents Card default');
ab_same(true, str_contains(alphablock_get_prompt(), '"list_icon": ""'), 'system prompt documents optional list icon');
ab_same(true, str_contains(alphablock_get_prompt(), '"header_class": ""'), 'system prompt documents optional header classes');
echo 'PASS: ' . $GLOBALS['ab_checks'] . " checks, including " . count($cases) . " complete 3.2.1 render comparisons.\n";
