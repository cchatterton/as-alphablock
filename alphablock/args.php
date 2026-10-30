<?php
/**
 * AlphaBlock query construction. Input values have already had tokens replaced
 * and types normalized. Query execution stays in template.php.
 */
function alphablock_base_args(array $values) {
	return [
		'posts_per_page'   => $values['order_by'] === 'rand' ? -1 : $values['total_posts'],
		'post_type'        => $values['post_type'] ?: 'any',
		'post_status'      => 'publish',
		'order'            => $values['sort_order'],
		'orderby'          => $values['order_by'],
		'post__not_in'     => $values['exclude_posts'],
		'suppress_filters' => false,
	];
}

/** Resolve a single row. Existing Meta/CPT query shapes are retained. */
function alphablock_filter_clause($condition) {
	if (!is_array($condition)) {
		return null;
	}
	$condition = array_replace([
		'filter_type' => 'Meta', 'key' => '', 'compare_as' => '=', 'value' => '',
	], $condition);
	if (!is_scalar($condition['key']) || trim((string) $condition['key']) === '') {
		return null;
	}
	$keys = explode('&', (string) $condition['key'], 2);
	$key_1 = trim($keys[0]);
	$key_2 = isset($keys[1]) ? trim($keys[1]) : $key_1;
	$compare = strtoupper((string) $condition['compare_as']);
	$value = $condition['value'];

	if ($condition['filter_type'] === 'Post') {
		return ['attribute' => ['key' => $key_1, 'value' => $value, 'compare' => $compare]];
	}
	if ($condition['filter_type'] === 'CPT') {
		if (is_scalar($value) && ctype_digit((string) $value)) {
			$slug = get_post_field('post_name', (int) $value);
			if (is_string($slug) && $slug !== '') {
				$value = $slug;
			}
		}
		$matches = $value !== '' ? get_posts([
			'name' => $value, 'post_type' => $key_2, 'post_status' => 'publish',
			'numberposts' => 1, 'fields' => 'ids',
		]) : [];
		return ['meta' => $matches ? [
			'key' => $key_1,
			'value' => $compare === 'LIKE' ? sprintf(':"%s";', $matches[0]) : $matches[0],
			'compare' => $compare,
		] : [
			'key' => $key_1, 'value' => '__alphablock_unmatched_cpt__', 'compare' => '=',
		]];
	}
	if ($condition['filter_type'] !== 'Meta') {
		return null;
	}
	if ($key_1 === 'relates_to' && $compare === '=' && is_scalar($value) && ctype_digit((string) $value)) {
		$value = (int) $value;
		return ['meta' => $value > 0 ? [
			'relation' => 'OR',
			['key' => $key_1, 'value' => '"' . $value . '"', 'compare' => 'LIKE'],
			['key' => $key_1, 'value' => 'i:' . $value . ';', 'compare' => 'LIKE'],
			['key' => $key_1, 'value' => $value, 'compare' => '='],
		] : [
			'key' => $key_1, 'value' => '__alphablock_no_owner__', 'compare' => '=',
		]];
	}
	return ['meta' => ['key' => $key_1, 'value' => $value, 'compare' => $compare]];
}

/** Combine rows without changing the native query for existing Meta/CPT blocks. */
function alphablock_condition_args(array $args, array $clauses, $relation) {
	if (!$clauses) {
		return $args;
	}
	if (!array_filter($clauses, static function ($clause) { return isset($clause['attribute']); })) {
		$args['meta_query'] = array_merge(['relation' => $relation], array_column($clauses, 'meta'));
	} else {
		$args['alphablock_conditions'] = ['relation' => $relation, 'clauses' => $clauses];
	}
	return $args;
}

/** Family selection retains the supplied 3.2.1 hierarchy and exclusion rules. */
function alphablock_family_args(array $args, array $values) {
	$selected = array_values(array_intersect(
		['parents', 'children', 'uncles_aunties', 'siblings', 'grandparents'],
		array_map('sanitize_key', (array) $values['family'])
	));
	$current = (int) $values['whose_family'];
	$parent = $current ? (int) wp_get_post_parent_id($current) : 0;
	$grandparent = $parent ? (int) wp_get_post_parent_id($parent) : 0;
	$get_children = static function ($id) use ($args) {
		return get_posts([
			'fields' => 'ids', 'posts_per_page' => -1, 'post_type' => $args['post_type'],
			'post_status' => 'publish', 'post_parent' => (int) $id, 'suppress_filters' => false,
		]);
	};
	$ids = [];
	if (in_array('parents', $selected, true) && $parent) {
		$ids[] = $parent;
	}
	if (in_array('children', $selected, true)) {
		$ids = array_merge($ids, $get_children($current));
	}
	if (in_array('siblings', $selected, true) && $parent) {
		$ids = array_merge($ids, array_diff($get_children($parent), [$current]));
	}
	if (in_array('uncles_aunties', $selected, true) && $parent && $grandparent) {
		$ids = array_merge($ids, array_diff($get_children($grandparent), [$parent, $current]));
	}
	if (in_array('grandparents', $selected, true) && $grandparent) {
		$ids[] = $grandparent;
	}
	$ids = array_values(array_unique(array_filter(array_map('intval', $ids))));
	$ids = array_values(array_diff($ids, array_merge([$current], $values['exclude_posts'])));
	$args['post__in'] = $ids ?: [0]; // An empty array would select all posts.
	unset($args['post__not_in']);
	return $args;
}

/** A Build plan keeps rows unresolved until their turn, avoiding unused lookups. */
function alphablock_build_args(array $values) {
	$args = alphablock_base_args($values);
	$mode = strtolower((string) $values['query_logic']);
	$plan = ['mode' => $mode, 'args' => $args, 'steps' => [], 'empty' => false];
	if ($mode === 'build') {
		$plan['steps'] = $values['filters'] ? array_values(array_filter($values['filters'], 'is_array')) : [null]; // No filters: base query fallback.
	} elseif ($mode === 'family') {
		$plan['args'] = alphablock_family_args($args, $values);
		$plan['empty'] = $plan['args']['post__in'] === [0];
	} elseif (in_array($mode, ['all', 'any'], true)) {
		$clauses = [];
		foreach ($values['filters'] as $condition) {
			$clause = alphablock_filter_clause($condition);
			if ($clause !== null) {
				$clauses[] = $clause;
			}
		}
		$plan['args'] = alphablock_condition_args($args, $clauses, $mode === 'all' ? 'AND' : 'OR');
	}
	return $plan;
}

/** Construct only the next waterfall query, with the current gap/exclusions. */
function alphablock_build_step_args(array $base, $condition, $gap, array $selected_ids) {
	if ($condition !== null) {
		$clause = alphablock_filter_clause($condition);
		if ($clause === null) {
			return null; // Malformed rows must not turn into unrestricted fallback queries.
		}
		$base = alphablock_condition_args($base, [$clause], 'AND');
	}
	$base['posts_per_page'] = $gap;
	$base['post__not_in'] = array_values(array_unique(array_merge($base['post__not_in'], $selected_ids)));
	return $base;
}

/** Safe, allowlisted comparisons against wp_posts columns for the new Post type. */
function alphablock_attribute_sql(array $attribute) {
	global $wpdb;
	$columns = [
		'id' => 'ID', 'author' => 'post_author', 'post_author' => 'post_author',
		'parent' => 'post_parent', 'post_parent' => 'post_parent',
		'status' => 'post_status', 'post_status' => 'post_status',
		'title' => 'post_title', 'post_title' => 'post_title',
		'slug' => 'post_name', 'post_name' => 'post_name',
		'date' => 'post_date', 'post_date' => 'post_date',
		'modified' => 'post_modified', 'post_modified' => 'post_modified',
	];
	$column = $columns[strtolower($attribute['key'])] ?? null;
	$compare = $attribute['compare'];
	$value = $attribute['value'];
	if (!$column || !is_scalar($value) || !in_array($compare, ['=', '!=', '>', '<', '>=', '<=', 'LIKE', 'NOT LIKE'], true)) {
		return '1=0';
	}
	$numeric = in_array($column, ['ID', 'post_author', 'post_parent'], true);
	if ($numeric && (!ctype_digit((string) $value) || in_array($compare, ['LIKE', 'NOT LIKE'], true))) {
		return '1=0';
	}
	if (in_array($compare, ['LIKE', 'NOT LIKE'], true)) {
		$value = '%' . $wpdb->esc_like((string) $value) . '%';
	}
	// Both the column and operator come exclusively from the allowlists above.
	return $wpdb->prepare("{$wpdb->posts}.{$column} {$compare} " . ($numeric ? '%d' : '%s'), $value);
}

/**
 * Scope additional SQL to queries carrying our explicit condition argument.
 * Subqueries let Any combine attributes and metadata without an inner meta join
 * incorrectly removing posts that only match the attribute branch.
 */
function alphablock_posts_where($where, $query) {
	global $wpdb;
	$conditions = $query->get('alphablock_conditions');
	if (!is_array($conditions) || empty($conditions['clauses'])) {
		return $where;
	}
	$parts = [];
	foreach ($conditions['clauses'] as $clause) {
		if (isset($clause['attribute'])) {
			$parts[] = '(' . alphablock_attribute_sql($clause['attribute']) . ')';
		} else {
			$meta_query = new WP_Meta_Query([$clause['meta']]);
			$sql = $meta_query->get_sql('post', $wpdb->posts, 'ID', $query);
			$parts[] = "{$wpdb->posts}.ID IN (SELECT {$wpdb->posts}.ID FROM {$wpdb->posts} {$sql['join']} WHERE 1=1 {$sql['where']})";
		}
	}
	$relation = $conditions['relation'] === 'OR' ? ' OR ' : ' AND ';
	return $where . ' AND (' . implode($relation, $parts) . ')';
}
add_filter('posts_where', 'alphablock_posts_where', 10, 2);
