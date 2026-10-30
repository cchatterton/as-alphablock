<?php
/**
 * AlphaBlock token definitions and bulk replacement.
 *
 * Values are resolved from the post containing the block, before processing.
 * Add fixed token names to this map or use the alphablock_tokens filter.
 */
function alphablock_get_tokens($post_id) {
	$slug = $post_id > 0
		? (string) get_post_field('post_name', $post_id)
		: '';

	return apply_filters('alphablock_tokens', [
		'%me%'       => (string) $post_id,
		'%me->ID%'   => (string) $post_id,
		'%me-slug%'  => $slug,
		'%me->slug%' => $slug, // Existing spelling remains supported.
	], $post_id);
}

/**
 * Replace tokens in string values at any depth, leaving keys and other types
 * intact. Unknown tokens stay literal; replacement text is never expanded again.
 *
 * Named metadata: %mine->meta_key% works anywhere. Like legacy %mine%, arrays
 * use their first item, WP_Post values use their ID, and missing values use ''.
 * Legacy %mine% still uses the filter's key, or the owner ID in whose_family.
 */
function alphablock_replace_tokens(array $values, $post_id) {
	$post_id = absint($post_id);
	$tokens = alphablock_get_tokens($post_id);
	$meta_values = [];

	$get_meta = static function ($key) use ($post_id, &$meta_values) {
		if (!array_key_exists($key, $meta_values)) {
			$value = $post_id > 0 && $key !== ''
				? get_post_meta($post_id, $key, true)
				: '';
			if (is_array($value)) {
				$value = reset($value);
			}
			if ($value instanceof WP_Post) {
				$value = $value->ID;
			}
			$meta_values[$key] = is_scalar($value) ? (string) $value : '';
		}
		return $meta_values[$key];
	};

	// Match only supported tokens, so unrelated '%' text cannot hide a token.
	$names = array_keys($tokens);
	$names[] = '%mine%';
	$pattern = '~' . implode('|', array_map(static function ($name) {
		return preg_quote($name, '~');
	}, $names)) . '|%mine->([^%]+)%~';

	$replace = static function ($value, $legacy_mine = null) use ($pattern, $tokens, $get_meta) {
		return preg_replace_callback($pattern, static function ($match) use ($tokens, $get_meta, $legacy_mine) {
			if (array_key_exists($match[0], $tokens)) {
				return (string) $tokens[$match[0]];
			}
			if ($match[0] === '%mine%') {
				return $legacy_mine !== null ? $legacy_mine : $match[0];
			}
			return $get_meta($match[1]);
		}, $value);
	};

	$walk = static function ($value, array $path = []) use (&$walk, $values, $replace, $get_meta, $post_id) {
		if (is_array($value)) {
			foreach ($value as $key => $item) {
				$value[$key] = $walk($item, array_merge($path, [$key]));
			}
			return $value;
		}
		if (!is_string($value)) {
			return $value;
		}

		$legacy_mine = null;
		if (strpos($value, '%mine%') !== false) {
			if ($path === ['whose_family']) {
				$legacy_mine = (string) $post_id;
			} elseif (count($path) === 3 && $path[0] === 'filters' && $path[2] === 'value') {
				// Resolve the key once, as in 3.2.1, before its metadata lookup.
				$key = $values['filters'][$path[1]]['key'] ?? '';
				$key = is_scalar($key) ? $replace((string) $key) : '';
				$legacy_mine = $get_meta(trim(explode('&', $key, 2)[0]));
			}
		}
		return $replace($value, $legacy_mine);
	};

	return $walk($values);
}
