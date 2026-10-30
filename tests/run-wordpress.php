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
echo 'PASS: ' . $GLOBALS['ab_checks'] . " checks, including " . count($cases) . " complete 3.2.1 render comparisons.\n";
