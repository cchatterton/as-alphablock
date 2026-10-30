<?php

/**
 * AlphaBlock JSON synchronisation.
 *
 * IMPORTANT:
 * This file belongs inside the AlphaBlock folder and is included by the
 * shared ACF Blocks registration function.
 */
require_once __DIR__ . '/prompt.php';

// This textarea belongs to the author's request, not the system instructions.
// Clear only the exact generated prompt that v3.3.0 may have saved here.
add_filter('acf/load_value/key=field_prompt', static function ($value) {
	if ($value === null || $value === false) {
		return '';
	}
	if (is_string($value) && trim($value) === trim(alphablock_get_prompt())) {
		return '';
	}
	return $value;
});

add_action('admin_footer', function () {
	?>
	<script id="alphablock-json-sync-debug">
	(function () {
		'use strict';

		var PREFIX = '[AlphaBlock JSON]';
		var BLOCK_SELECTOR = '.acf-block-fields[data-block-id]';

		var FIELD_MAP = {
			header_level: {
				key: 'field_header_level',
				type: 'number'
			},
			header: {
				key: 'field_header',
				type: 'string'
			},
			total_posts: {
				key: 'field_total_posts',
				type: 'number'
			},
			post_type: {
				key: 'field_post_type',
				type: 'nullable'
			},
			order_by: {
				key: 'field_order_by',
				type: 'nullable'
			},
			sort_order: {
				key: 'field_sort_order',
				type: 'nullable'
			},
			query_logic: {
				key: 'field_query_logic_q',
				type: 'nullable'
			},
			posts_per_row: {
				key: 'field_posts_per_row',
				type: 'number'
			},
			card: {
				key: 'field_card',
				type: 'id'
			},
			layout: {
				key: 'field_layout',
				type: 'nullable'
			},
			mobile_layout: {
				key: 'field_mobile_layout',
				type: 'nullable'
			},
			show_links: {
				key: 'field_show_links',
				type: 'array'
			},
			whose_family: {
				key: 'field_whose_family',
				type: 'family_reference'
			},
			family: {
				key: 'field_family',
				type: 'array'
			},
			include_posts: {
				key: 'field_include_posts',
				type: 'ids'
			},
			exclude_posts: {
				key: 'field_exclude_posts',
				type: 'ids'
			}
		};

		var FILTER_MAP = {
			filter_type: 'field_filters_filter_type',
			key: 'field_filters_key',
			compare_as: 'field_filters_compare_as',
			value: 'field_filters_value'
		};

		var initializedBlocks = new WeakSet();
		var blockStates = new WeakMap();

		console.info(PREFIX, 'Script loaded', {
			url: window.location.href,
			acfAvailable: Boolean(window.acf),
			jQueryAvailable: Boolean(window.jQuery)
		});

		function getState(block) {
			var state = blockStates.get(block);

			if (!state) {
				state = {
					applyingJson: false,
					writingJson: false,
					fieldsTimer: null,
					jsonTimer: null,
					tokenValues: {}
				};

				blockStates.set(block, state);
			}

			return state;
		}

		function hasOwn(object, property) {
			return Object.prototype.hasOwnProperty.call(
				object,
				property
			);
		}

		function escapeSelectorValue(value) {
			if (
				window.CSS &&
				typeof window.CSS.escape === 'function'
			) {
				return window.CSS.escape(value);
			}

			return String(value).replace(/"/g, '\\"');
		}

		function findField(block, key) {
			return block.querySelector(
				'.acf-field[data-key="' +
				escapeSelectorValue(key) +
				'"]'
			);
		}

		function findBlock(element) {
			if (!element || !element.closest) {
				return null;
			}

			return element.closest(BLOCK_SELECTOR);
		}

		function findFieldInput(field) {
			if (!field) {
				return null;
			}

			for (
				var index = 0;
				index < field.children.length;
				index++
			) {
				if (
					field.children[index].classList &&
					field.children[index].classList.contains('acf-input')
				) {
					return field.children[index];
				}
			}

			return null;
		}

		function toInteger(value, fallback) {
			if (
				value === '' ||
				value === null ||
				value === undefined
			) {
				return fallback;
			}

			var parsed = parseInt(value, 10);

			return Number.isNaN(parsed)
				? fallback
				: parsed;
		}

		function toArray(value) {
			if (Array.isArray(value)) {
				return value.filter(function (item) {
					return (
						item !== '' &&
						item !== null &&
						item !== undefined
					);
				});
			}

			if (
				value === '' ||
				value === null ||
				value === undefined
			) {
				return [];
			}

			return [value];
		}

		function toIds(value) {
			return toArray(value)
				.map(function (item) {
					return toInteger(item, null);
				})
				.filter(function (item) {
					return item !== null;
				});
		}

		function normalizeValue(type, value) {
			switch (type) {
				case 'number':
				return toInteger(value, null);

				case 'id':
					return toInteger(value, null);

				case 'ids':
					return toIds(value);

				case 'array':
					return toArray(value);

				case 'family_reference':
					var reference =
						value === null ||
						value === undefined
							? ''
							: String(value)
								.trim();

					if (
						reference.toLowerCase() === '%me%' ||
						reference.toLowerCase() === '%mine%'
					) {
						return '%me%';
					}

					// Preserve named/unknown tokens and their case in saved JSON.
					if (/%[^%]+%/.test(reference)) {
						return reference;
					}

					if (/^\d+$/.test(reference)) {
						return parseInt(reference, 10);
					}

					return '%me%';

				case 'string':
					if (
						value === null ||
						value === undefined
					) {
						return '';
					}

					return String(value);

				default:
					if (
						value === '' ||
						value === undefined
					) {
						return null;
					}

					return value;
			}
		}

		function readField(block, definition) {
			var field = findField(block, definition.key);
			var inputArea = findFieldInput(field);
			var value = null;

			if (!field || !inputArea) {
				console.warn(
					PREFIX,
					'Could not find field',
					definition.key
				);

				return normalizeValue(
					definition.type,
					null
				);
			}

			if (definition.type === 'array') {
				value = Array.from(
					inputArea.querySelectorAll(
						'input[type="checkbox"]:checked'
					)
				).map(function (checkbox) {
					return checkbox.value;
				});
			} else if (definition.type === 'ids') {
				value = Array.from(
					inputArea.querySelectorAll(
						'.values-list .acf-rel-item[data-id]'
					)
				).map(function (item) {
					return item.getAttribute('data-id');
				});
			} else if (definition.type === 'number') {
				var numberInput = inputArea.querySelector(
					'input[type="range"], input[type="number"]'
				);

				value = numberInput
					? numberInput.value
					: null;
			} else if (definition.type === 'id') {
				var idSelect = inputArea.querySelector('select');
				var idHidden = inputArea.querySelector(
					'input[type="hidden"]'
				);

				if (idSelect && idSelect.value) {
					value = idSelect.value;
				} else if (idHidden) {
					value = idHidden.value;
				}
			} else {
				var ordinaryInput = inputArea.querySelector(
					'select, textarea, input:not([type="hidden"])'
				);

				value = ordinaryInput
					? ordinaryInput.value
					: null;
			}

			return normalizeValue(
				definition.type,
				value
			);
		}

		function readFilters(block) {
			var repeater = findField(
				block,
				'field_filters'
			);

			if (!repeater) {
				return [];
			}

			var rows = repeater.querySelectorAll(
				'tr.acf-row:not(.acf-clone)'
			);

			return Array.from(rows).map(function (row) {
				var result = {};

				Object.keys(FILTER_MAP).forEach(
					function (name) {
						var field = row.querySelector(
							'.acf-field[data-key="' +
								FILTER_MAP[name] +
								'"]'
						);

						var input = field
							? field.querySelector(
								'select, input[type="text"], textarea'
							)
							: null;

						result[name] = input
							? input.value
							: '';
					}
				);

				return result;
			});
		}

		function readBlock(block) {
			var data = {
				header_level: readField(
					block,
					FIELD_MAP.header_level
				),
				header: readField(
					block,
					FIELD_MAP.header
				),
				total_posts: readField(
					block,
					FIELD_MAP.total_posts
				),
				post_type: readField(
					block,
					FIELD_MAP.post_type
				),
				order_by: readField(
					block,
					FIELD_MAP.order_by
				),
				sort_order: readField(
					block,
					FIELD_MAP.sort_order
				),
				query_logic: readField(
					block,
					FIELD_MAP.query_logic
				),
				filters: readFilters(block),
				posts_per_row: readField(
					block,
					FIELD_MAP.posts_per_row
				),
				card: readField(
					block,
					FIELD_MAP.card
				),
				layout: readField(
					block,
					FIELD_MAP.layout
				),
				mobile_layout: readField(
					block,
					FIELD_MAP.mobile_layout
				),
				show_links: readField(
					block,
					FIELD_MAP.show_links
				),
				whose_family: readField(
					block,
					FIELD_MAP.whose_family
				),
				family: readField(
					block,
					FIELD_MAP.family
				),
				include_posts: readField(
					block,
					FIELD_MAP.include_posts
				),
				exclude_posts: readField(
					block,
					FIELD_MAP.exclude_posts
				)
			};
			// Typed ACF controls cannot represent every JSON token (e.g. a range).
			// Keep those raw values until the author explicitly edits that field.
			var tokenValues = getState(block).tokenValues;
			Object.keys(tokenValues).forEach(function (name) {
				data[name] = tokenValues[name];
			});
			return data;
		}

		function rememberTokenValues(block, data) {
			function containsToken(value) {
				if (typeof value === 'string') {
					return /%[^%]+%/.test(value);
				}
				return value && typeof value === 'object' &&
					Object.keys(value).some(function (key) {
						return containsToken(value[key]);
					});
			}
			var remembered = {};
			Object.keys(data).forEach(function (name) {
				if ((hasOwn(FIELD_MAP, name) || name === 'filters') && containsToken(data[name])) {
					remembered[name] = data[name];
				}
			});
			getState(block).tokenValues = remembered;
		}

		function getJsonTextarea(block) {
			var jsonField = findField(
				block,
				'field_json'
			);

			return jsonField
				? jsonField.querySelector('textarea')
				: null;
		}

		function dispatchFieldEvents(input) {
			if (!input) {
				return;
			}

			input.dispatchEvent(
				new Event('input', {
					bubbles: true
				})
			);

			input.dispatchEvent(
				new Event('change', {
					bubbles: true
				})
			);

			if (window.jQuery) {
				window.jQuery(input).trigger('change');
			}
		}

		function markJsonValid(textarea) {
			textarea.setCustomValidity('');
			textarea.style.borderColor = '';
			textarea.title = '';
		}

		function markJsonInvalid(textarea, error) {
			var message = 'Invalid JSON';

			if (error && error.message) {
				message += ': ' + error.message;
			}

			textarea.setCustomValidity(message);
			textarea.style.borderColor = '#d63638';
			textarea.title = message;

			console.error(PREFIX, message);
		}

		function writeFieldsToJson(block) {
			var state = getState(block);

			if (state.applyingJson) {
				return;
			}

			var textarea = getJsonTextarea(block);

			if (!textarea) {
				console.error(
					PREFIX,
					'JSON textarea was not found',
					block
				);

				return;
			}

			var data = readBlock(block);
			var json = JSON.stringify(data, null, 2);

			state.writingJson = true;

			if (textarea.value !== json) {
				textarea.value = json;
				dispatchFieldEvents(textarea);
			}

			state.writingJson = false;
			markJsonValid(textarea);

			console.info(
				PREFIX,
				'JSON updated from fields',
				data
			);
		}

		function scheduleFieldsToJson(block) {
			var state = getState(block);

			if (state.applyingJson) {
				return;
			}

			window.clearTimeout(state.fieldsTimer);

			state.fieldsTimer = window.setTimeout(
				function () {
					writeFieldsToJson(block);
				},
				100
			);
		}

		function getAcfField(fieldElement) {
			if (
				!fieldElement ||
				!window.acf ||
				!window.jQuery ||
				typeof window.acf.getField !== 'function'
			) {
				return null;
			}

			try {
				return window.acf.getField(
					window.jQuery(fieldElement)
				);
			} catch (error) {
				console.warn(
					PREFIX,
					'Unable to get ACF field instance',
					error
				);

				return null;
			}
		}

		function setOrdinaryField(
			block,
			definition,
			value
		) {
			var fieldElement = findField(
				block,
				definition.key
			);

			if (!fieldElement) {
				console.warn(
					PREFIX,
					'Cannot set missing field',
					definition.key
				);

				return;
			}

			var inputArea = findFieldInput(fieldElement);

			if (!inputArea) {
				return;
			}

			if (definition.type === 'array') {
				var selectedValues = toArray(value).map(String);

				inputArea
					.querySelectorAll('input[type="checkbox"]')
					.forEach(function (checkbox) {
						checkbox.checked =
							selectedValues.includes(
								String(checkbox.value)
							);

						dispatchFieldEvents(checkbox);
					});

				return;
			}

			if (definition.type === 'ids') {
				var relationshipField = getAcfField(
					fieldElement
				);

				if (
					relationshipField &&
					typeof relationshipField.val === 'function'
				) {
					relationshipField.val(
						toIds(value).map(String)
					);

					if (
						typeof relationshipField.$input ===
						'function'
					) {
						relationshipField
							.$input()
							.trigger('change');
					}
				} else {
					console.warn(
						PREFIX,
						'Relationship field requires the ACF JS API',
						definition.key
					);
				}

				return;
			}

			if (definition.type === 'id') {
				var objectField = getAcfField(fieldElement);

				if (
					objectField &&
					typeof objectField.val === 'function'
				) {
					objectField.val(
						value === null ||
						value === undefined
							? ''
							: String(value)
					);

					if (
						typeof objectField.$input ===
						'function'
					) {
						objectField
							.$input()
							.trigger('change');
					}

					return;
				}
			}

			if (definition.type === 'number') {
				var range = inputArea.querySelector(
					'input[type="range"]'
				);

				var number = inputArea.querySelector(
					'input[type="number"]'
				);

				var numberValue =
					value === null ||
					value === undefined
						? ''
						: String(value);

				if (range) {
					range.value = numberValue;
					dispatchFieldEvents(range);
				}

				if (number) {
					number.value = numberValue;
					dispatchFieldEvents(number);
				}

				return;
			}

			var input = inputArea.querySelector(
				'select, textarea, input:not([type="hidden"])'
			);

			if (!input) {
				return;
			}

			input.value =
				value === null ||
				value === undefined
					? ''
					: String(value);

			dispatchFieldEvents(input);
		}

		function clearRepeater(repeater) {
			if (
				!repeater ||
				typeof repeater.$rows !== 'function' ||
				typeof repeater.remove !== 'function'
			) {
				return;
			}

			var rows = repeater.$rows();

			for (
				var index = rows.length - 1;
				index >= 0;
				index--
			) {
				repeater.remove(rows.eq(index));
			}
		}

		function setRepeaterSubField(
			row,
			key,
			value
		) {
			if (!window.jQuery) {
				return;
			}

			var fieldElement = window.jQuery(row)
				.find(
					'.acf-field[data-key="' +
						key +
						'"]'
				)
				.first();

			var field = fieldElement.length
				? getAcfField(fieldElement[0])
				: null;

			if (
				!field ||
				typeof field.val !== 'function'
			) {
				return;
			}

			field.val(
				value === null ||
				value === undefined
					? ''
					: value
			);

			if (typeof field.$input === 'function') {
				field.$input().trigger('change');
			}
		}

		function setFilters(block, rows) {
			var repeaterElement = findField(
				block,
				'field_filters'
			);

			var repeater = getAcfField(repeaterElement);

			if (
				!repeater ||
				typeof repeater.add !== 'function' ||
				typeof repeater.remove !== 'function'
			) {
				console.warn(
					PREFIX,
					'Repeater field requires the ACF JS API'
				);

				return;
			}

			rows = Array.isArray(rows) ? rows : [];

			clearRepeater(repeater);

			rows.forEach(function (rowData) {
				if (
					!rowData ||
					typeof rowData !== 'object' ||
					Array.isArray(rowData)
				) {
					return;
				}

				var row = repeater.add();

				Object.keys(FILTER_MAP).forEach(
					function (name) {
						setRepeaterSubField(
							row,
							FILTER_MAP[name],
							hasOwn(rowData, name)
								? rowData[name]
								: ''
						);
					}
				);
			});
		}

		function applyJsonToFields(block, data) {
			var state = getState(block);

			state.applyingJson = true;

			try {
				Object.keys(FIELD_MAP).forEach(
					function (name) {
						if (!hasOwn(data, name)) {
							return;
						}

						setOrdinaryField(
							block,
							FIELD_MAP[name],
							data[name]
						);
					}
				);

				if (hasOwn(data, 'filters')) {
					setFilters(block, data.filters);
				}
			} finally {
				state.applyingJson = false;
			}

			console.info(
				PREFIX,
				'Fields updated from JSON',
				data
			);
		}

		function parseJson(block, formatAfterApply) {
			var state = getState(block);

			if (state.writingJson) {
				return;
			}

			var textarea = getJsonTextarea(block);

			if (!textarea) {
				return;
			}

			var raw = String(
				textarea.value || ''
			).trim();

			if (!raw) {
				markJsonInvalid(
					textarea,
					new Error(
						'The JSON textarea is empty'
					)
				);

				return;
			}

			try {
				var data = JSON.parse(raw);

				if (
					!data ||
					typeof data !== 'object' ||
					Array.isArray(data)
				) {
					throw new Error(
						'The top-level JSON value must be an object'
					);
				}

				markJsonValid(textarea);
				rememberTokenValues(block, data);
				applyJsonToFields(block, data);

				if (formatAfterApply) {
					textarea.value = JSON.stringify(
						readBlock(block),
						null,
						2
					);
				}
			} catch (error) {
				markJsonInvalid(textarea, error);
			}
		}

		function scheduleJsonToFields(block) {
			var state = getState(block);

			if (state.writingJson) {
				return;
			}

			window.clearTimeout(state.jsonTimer);

			state.jsonTimer = window.setTimeout(
				function () {
					parseJson(block, false);
				},
				400
			);
		}

		function handleFieldEvent(event) {
			var target = event.target;

			if (!target || !target.closest) {
				return;
			}

			var block = findBlock(target);

			if (!block) {
				return;
			}

			var field = target.closest('.acf-field');

			if (!field) {
				return;
			}

			var state = getState(block);
			if (state.applyingJson || state.writingJson) {
				return;
			}

			var key = field.getAttribute('data-key');

			if (key === 'field_prompt') {
				return;
			}

			if (key === 'field_json') {
				scheduleJsonToFields(block);
				return;
			}

			Object.keys(FIELD_MAP).forEach(function (name) {
				if (FIELD_MAP[name].key === key) {
					delete state.tokenValues[name];
				}
			});
			if (key === 'field_filters' || key.indexOf('field_filters_') === 0) {
				delete state.tokenValues.filters;
			}
			scheduleFieldsToJson(block);
		}

		function initializeBlock(block) {
			if (
				!block ||
				initializedBlocks.has(block)
			) {
				return;
			}

			if (!getJsonTextarea(block)) {
				return;
			}

			initializedBlocks.add(block);
			getState(block);

			block.addEventListener(
				'input',
				handleFieldEvent,
				true
			);

			block.addEventListener(
				'change',
				handleFieldEvent,
				true
			);

			var jsonTextarea = getJsonTextarea(block);
			try {
				var initialData = JSON.parse(jsonTextarea.value);
				if (initialData && typeof initialData === 'object' && !Array.isArray(initialData)) {
					rememberTokenValues(block, initialData);
				}
			} catch (error) {
				// Empty/legacy JSON still bootstraps from the existing field controls.
			}


			jsonTextarea.addEventListener(
				'blur',
				function () {
					parseJson(block, true);
				}
			);

			console.info(
				PREFIX,
				'AlphaBlock initialized',
				{
					blockId:
						block.getAttribute('data-block-id'),
					block: block
				}
			);

			window.setTimeout(function () {
				writeFieldsToJson(block);
			}, 0);

			window.setTimeout(function () {
				writeFieldsToJson(block);
			}, 250);
		}

		function scanForBlocks(root) {
			if (!root) {
				return;
			}

			if (
				root.nodeType === 1 &&
				root.matches &&
				root.matches(BLOCK_SELECTOR)
			) {
				initializeBlock(root);
			}

			if (!root.querySelectorAll) {
				return;
			}

			root
				.querySelectorAll(BLOCK_SELECTOR)
				.forEach(initializeBlock);
		}

		if (document.readyState === 'loading') {
			document.addEventListener(
				'DOMContentLoaded',
				function () {
					scanForBlocks(document);
				}
			);
		} else {
			scanForBlocks(document);
		}

		var observer = new MutationObserver(
			function (mutations) {
				mutations.forEach(function (mutation) {
					mutation.addedNodes.forEach(
						function (node) {
							if (node.nodeType === 1) {
								scanForBlocks(node);
							}
						}
					);
				});
			}
		);

		observer.observe(document.documentElement, {
			childList: true,
			subtree: true
		});

		console.info(
			PREFIX,
			'Mutation observer started'
		);
	})();
	</script>
	<?php
}, 99);
