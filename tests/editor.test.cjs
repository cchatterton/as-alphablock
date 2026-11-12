const { test } = require('node:test');
const assert = require('node:assert/strict');
const fs = require('node:fs');
const path = require('node:path');
const { JSDOM, VirtualConsole } = require('jsdom');
const root = path.resolve(__dirname, '..');
const group = JSON.parse(fs.readFileSync(path.join(root, 'alphablock/alphablock.json'), 'utf8'));
const oldGroup = JSON.parse(fs.readFileSync(path.join(__dirname, 'fixtures/fields-3.2.0.json'), 'utf8'));
const source = fs.readFileSync(path.join(root, 'alphablock/block.php'), 'utf8').match(/<script[^>]*>([\s\S]*?)<\/script>/)[1];
const delay = ms => new Promise(resolve => setTimeout(resolve, ms));
const escape = value => String(value ?? '').replaceAll('&', '&amp;').replaceAll('"', '&quot;').replaceAll('<', '&lt;').replaceAll('>', '&gt;');
const base = {
  header_level: 2, header: '', total_posts: 3, post_type: 'stories', order_by: 'title', sort_order: 'ASC',
  query_logic: 'All', filters: [], posts_per_row: 3, card: 12, layout: 'Wrap', mobile_layout: 'Wrap',
  show_links: [], whose_family: '%me%', family: ['children'], include_posts: [], exclude_posts: []
};
function control(field, value) {
  const attrs = `data-key="${escape(field.key)}" class="acf-field"`;
  let inner = '';
  switch (field.type) {
    case 'range': inner = `<input type="range" min="${field.min}" max="${field.max}" value="${escape(value)}"><input type="number" value="${escape(value)}">`; break;
    case 'text': inner = `<input type="text" value="${escape(value)}">`; break;
    case 'textarea': inner = `<textarea>${escape(value)}</textarea>`; break;
    case 'button_group':
      value = value ?? field.default_value;
      inner = Object.entries(field.choices).map(([key, label]) => `<label><input type="radio" name="${field.key}" value="${key}"${value === key ? ' checked' : ''}>${label}</label>`).join(''); break;
    case 'select':
      inner = '<select>' + (field.allow_null ? '<option value="">Any post type</option>' : '') + Object.entries(field.choices).map(([key, label]) => `<option value="${escape(key)}"${String(value) === key ? ' selected' : ''}>${escape(label)}</option>`).join('') + '</select>'; break;
    case 'post_object': inner = `<select><option value="">Select</option><option value="12"${String(value) === '12' ? ' selected' : ''}>Test card</option></select>`; break;
    case 'checkbox': inner = Object.keys(field.choices).map(key => `<input type="checkbox" value="${escape(key)}"${(value || []).includes(key) ? ' checked' : ''}>`).join(''); break;
    case 'relationship': inner = '<div class="values-list"></div>'; break;
    case 'repeater': inner = '<table><tbody>' + (value || []).map(row => '<tr class="acf-row">' + field.sub_fields.map(sub => `<td>${control(sub, row[sub.name])}</td>`).join('') + '</tr>').join('') + '</tbody></table>'; break;
    default: return '';
  }
  return `<div ${attrs}><div class="acf-input">${inner}</div></div>`;
}
function editor(values, fieldOverrides = {}, options = {}) {
  const config = { ...base, ...values };
  const form = { ...config, ...fieldOverrides, json: JSON.stringify(config), prompt: 'My saved prompt' };
  const html = '<div class="acf-block-fields" data-block-id="test">' + group.fields.map(field => control(field, form[field.name])).join('') + '</div>';
  const dom = new JSDOM(html, { runScripts: 'outside-only', url: 'https://example.invalid/wp-admin/post.php', virtualConsole: new VirtualConsole() });
  if (options.jQuery) { dom.window.jQuery = dom.window.$ = require('jquery')(dom.window); }
  dom.window.eval(source);
  dom.window.document.dispatchEvent(new dom.window.Event('DOMContentLoaded'));
  const input = key => dom.window.document.querySelector(`.acf-field[data-key="${key}"] .acf-input`).querySelector('textarea, select, input:not([type="hidden"])');
  return { dom, input, json: () => JSON.parse(input('field_json').value) };
}
function flatten(fields) {
  return fields.flatMap(field => [field, ...flatten(field.sub_fields || [])]);
}
test('existing saved field keys, names and types are preserved', () => {
  const fields = flatten(group.fields);
  const keys = fields.map(field => field.key);
  assert.equal(new Set(keys).size, keys.length);
  for (const old of flatten(oldGroup.fields).filter(field => !['tab', 'message'].includes(field.type))) {
    const current = fields.find(field => field.key === old.key);
    assert.ok(current, old.key);
    assert.equal(current.name, old.name);
    assert.equal(current.type, old.key === 'field_header_level' ? 'select' : old.type);
    if (old.choices) assert.deepEqual(Object.keys(current.choices).filter(key => key !== 'Post'), Object.keys(old.choices));
  }
});
test('header tag selection preserves numeric levels and writes optional classes', async () => {
  const field = group.fields.find(item => item.key === 'field_header_level');
  assert.deepEqual(Object.keys(field.choices), ['1', '2', '3', '4', '5', '6', 'span', 'p']);
  assert.equal(group.fields.find(item => item.key === 'field_header_class').name, 'header_class');
  const ui = editor({ header_level: 4, header: 'Existing heading' });
  try {
    await delay(380);
    assert.equal(ui.input('field_header_level').value, '4');
    assert.equal(ui.json().header_level, 4);
    assert.equal(ui.json().header_class, '');
    ui.input('field_header_level').value = 'span';
    ui.input('field_header_level').dispatchEvent(new ui.dom.window.Event('change', { bubbles: true }));
    ui.input('field_header_class').value = 'feature-heading large';
    ui.input('field_header_class').dispatchEvent(new ui.dom.window.Event('input', { bubbles: true }));
    await delay(180);
    assert.equal(ui.json().header_level, 'span');
    assert.equal(ui.json().header_class, 'feature-heading large');
    ui.input('field_header_level').value = '6';
    ui.input('field_header_level').dispatchEvent(new ui.dom.window.Event('change', { bubbles: true }));
    await delay(180);
    assert.equal(ui.json().header_level, 6);
  } finally { ui.dom.window.close(); }
});
test('manual selections share one tab, with explanations after the controls', () => {
  const fields = group.fields;
  const manual = fields.findIndex(field => field.key === 'field_tab_includes');
  assert.equal(fields[manual].label, 'Manual Selection');
  assert.equal(fields[manual + 1].key, 'field_include_posts');
  assert.equal(fields[manual + 2].key, 'field_exclude_posts');
  assert.ok(!fields.some(field => field.key === 'field_tab_excludes'));
  const choices = flatten(fields).find(field => field.key === 'field_filters_filter_type').choices;
  assert.deepEqual(choices, { CPT: 'Persona Targets', Meta: 'Meta', Post: 'Post Attributes' });
  assert.equal(fields.find(field => field.key === 'field_post_type').placeholder, 'Any post type');
  for (const [before, after] of [['field_query_logic_q', 'field_alphablock_post_query_help'], ['field_filters', 'field_alphablock_query_logic_help'], ['field_family', 'field_alphablock_family_help']]) {
    assert.equal(fields[fields.findIndex(field => field.key === before) + 1].key, after);
  }
  assert.equal(fields.find(field => field.key === 'field_whose_family').instructions, '');
});
test('Family tokens survive opening the editor and synchronising JSON', async () => {
  for (const token of ['%me%', '%me->ID%', '%me-slug%', '%me->slug%', '%mine->My_Parent_ID%', '%future_token%']) {
    const ui = editor({ whose_family: token });
    try { await delay(380); assert.equal(ui.json().whose_family, token); }
    finally { ui.dom.window.close(); }
  }
});
test('raw JSON tokens survive incompatible controls and unrelated edits', async () => {
  const ui = editor({ total_posts: '%mine->quota%', whose_family: '%mine->related_parent%' }, { total_posts: 3, whose_family: '%me%' });
  try {
    await delay(380);
    assert.equal(ui.json().total_posts, '%mine->quota%');
    assert.equal(ui.json().whose_family, '%mine->related_parent%');
    ui.input('field_header').value = 'New heading';
    ui.input('field_header').dispatchEvent(new ui.dom.window.Event('input', { bubbles: true }));
    await delay(180);
    assert.equal(ui.json().header, 'New heading');
    assert.equal(ui.json().total_posts, '%mine->quota%');
    // An intentional edit to this control replaces its saved token.
    ui.input('field_total_posts').value = '5';
    ui.input('field_total_posts').dispatchEvent(new ui.dom.window.Event('input', { bubbles: true }));
    await delay(180);
    assert.equal(ui.json().total_posts, 5);
  } finally { ui.dom.window.close(); }
});
test('JSON edits and blur retain the token spelling and Post filter value', async () => {
  const ui = editor({ filters: [{ filter_type: 'Post', key: 'author', compare_as: '=', value: '%me->ID%' }] });
  try {
    await delay(380);
    assert.equal(ui.json().filters[0].filter_type, 'Post');
    const next = { ...ui.json(), whose_family: '%mine->Parent_ID%' };
    ui.input('field_json').value = JSON.stringify(next);
    ui.input('field_json').dispatchEvent(new ui.dom.window.Event('input', { bubbles: true }));
    await delay(480);
    ui.input('field_json').dispatchEvent(new ui.dom.window.Event('blur'));
    assert.equal(ui.input('field_whose_family').value, '%mine->Parent_ID%');
    assert.equal(ui.json().whose_family, '%mine->Parent_ID%');
    assert.equal(ui.json().filters[0].value, '%me->ID%');
    assert.equal(ui.input('field_prompt').value, 'My saved prompt');
  } finally { ui.dom.window.close(); }
});

test('NLP request input is blank by default and its example is only a placeholder', () => {
  const field = group.fields.find(field => field.key === 'field_prompt');
  assert.equal(group.fields.find(field => field.key === 'field_tab_prompt').label, 'Prompt');
  assert.equal(field.name, 'prompt');
  assert.equal(field.label, 'Your request');
  assert.equal(field.default_value, '');
  assert.ok(field.placeholder.includes('state = nsw'));
  assert.ok(!field.placeholder.includes('"query_logic"'));
  const ui = editor({});
  try { assert.equal(ui.input('field_prompt').value, 'My saved prompt'); }
  finally { ui.dom.window.close(); }
});

test('debug defaults to zero and manual JSON changes survive field saves and blur', async () => {
  const ui = editor({});
  try {
    await delay(380);
    assert.equal(ui.json().debug, 0);
    ui.input('field_json').value = JSON.stringify({ ...ui.json(), debug: 1 });
    ui.input('field_json').dispatchEvent(new ui.dom.window.Event('input', { bubbles: true }));
    await delay(480);
    ui.input('field_json').dispatchEvent(new ui.dom.window.Event('blur'));
    assert.equal(ui.json().debug, 1);
    ui.input('field_header').value = 'Debugging this block';
    ui.input('field_header').dispatchEvent(new ui.dom.window.Event('input', { bubbles: true }));
    await delay(180);
    assert.equal(ui.json().debug, 1);
    ui.input('field_json').value = JSON.stringify({ ...ui.json(), debug: 0 });
    ui.input('field_json').dispatchEvent(new ui.dom.window.Event('input', { bubbles: true }));
    await delay(480);
    ui.input('field_json').dispatchEvent(new ui.dom.window.Event('blur'));
    assert.equal(ui.json().debug, 0);
  } finally { ui.dom.window.close(); }
});
test('debug one survives initial editor synchronisation', async () => {
  const ui = editor({ debug: 1 });
  try { await delay(380); assert.equal(ui.json().debug, 1); }
  finally { ui.dom.window.close(); }
});

test('posts per row allows -1 and preserves it through field and JSON edits', async () => {
  const field = group.fields.find(field => field.key === 'field_posts_per_row');
  assert.equal(field.min, -1);
  assert.equal(field.max, 12);
  assert.equal(field.default_value, 3);
  assert.equal(field.instructions, '');
  const helpIndex = group.fields.findIndex(field => field.key === 'field_alphablock_show_posts_help');
  const help = group.fields[helpIndex];
  assert.equal(group.fields[helpIndex - 1].key, 'field_alphablock_list_icon');
  assert.equal(group.fields[helpIndex + 1].key, 'field_tab_family');
  assert.equal(help.type, 'message');
  assert.equal(help.wrapper.width, '100');
  assert.ok(help.message.includes('one row'));
  const ui = editor({ posts_per_row: -1 });
  try {
    await delay(380);
    assert.equal(ui.json().posts_per_row, -1);
    ui.input('field_header').value = 'All in one row';
    ui.input('field_header').dispatchEvent(new ui.dom.window.Event('input', { bubbles: true }));
    await delay(180);
    assert.equal(ui.json().posts_per_row, -1);
    ui.input('field_json').value = JSON.stringify({ ...ui.json(), posts_per_row: 4 });
    ui.input('field_json').dispatchEvent(new ui.dom.window.Event('input', { bubbles: true }));
    await delay(480);
    assert.equal(ui.input('field_posts_per_row').value, '4');
    ui.input('field_json').value = JSON.stringify({ ...ui.json(), posts_per_row: -1 });
    ui.input('field_json').dispatchEvent(new ui.dom.window.Event('input', { bubbles: true }));
    await delay(480);
    ui.input('field_json').dispatchEvent(new ui.dom.window.Event('blur'));
    assert.equal(ui.input('field_posts_per_row').value, '-1');
    assert.equal(ui.json().posts_per_row, -1);
  } finally { ui.dom.window.close(); }
});

function setMode(ui, mode) {
  const radio = ui.dom.window.document.querySelector(`.acf-field[data-key="field_alphablock_show_as"] input[value="${mode}"]`);
  radio.checked = true;
  radio.dispatchEvent(new ui.dom.window.Event('change', { bubbles: true }));
}
test('Card/List conditions default to Card and keep all four List inputs optional', () => {
  const mode = group.fields.find(field => field.name === 'show_as');
  assert.equal(mode.type, 'button_group');
  assert.equal(mode.default_value, 'card');
  assert.deepEqual(mode.choices, { card: 'Card', list: 'List' });
  const cardNames = ['posts_per_row', 'card', 'layout', 'mobile_layout', 'show_links'];
  for (const name of cardNames) {
    const field = group.fields.find(field => field.name === name);
    assert.ok(field.conditional_logic.every(rules => rules.some(rule => rule.field === mode.key && rule.value === 'card')));
  }
  const links = group.fields.find(field => field.name === 'show_links');
  assert.ok(links.conditional_logic[0].some(rule => rule.field === 'field_layout' && rule.value === 'Wrap'));
  for (const name of ['list_ul_class', 'list_li_class', 'list_a_class', 'list_icon']) {
    const field = group.fields.find(field => field.name === name);
    assert.equal(field.required, 0);
    assert.equal(field.default_value, '');
    assert.deepEqual(field.conditional_logic, [[{ field: mode.key, operator: '==', value: 'list' }]]);
  }
});
test('legacy editor configurations gain Card as the default without changing card settings', async () => {
  const ui = editor({ card: 12, layout: 'Carousel', mobile_layout: 'Swipe', posts_per_row: -1 });
  try {
    await delay(380);
    assert.equal(ui.json().show_as, 'card');
    assert.equal(ui.json().card, 12);
    assert.equal(ui.json().layout, 'Carousel');
    assert.equal(ui.json().mobile_layout, 'Swipe');
    assert.equal(ui.json().posts_per_row, -1);
    assert.equal(ui.json().list_icon, '');
  } finally { ui.dom.window.close(); }
});
test('switching modes preserves card settings and edited List classes', async () => {
  const ui = editor({ show_as: 'card', card: 12, layout: 'Carousel', mobile_layout: 'Swipe', posts_per_row: -1, list_ul_class: 'initial-list' });
  try {
    await delay(380);
    setMode(ui, 'list'); await delay(180);
    assert.equal(ui.json().show_as, 'list');
    assert.equal(ui.json().card, 12);
    assert.equal(ui.json().layout, 'Carousel');
    ui.input('field_alphablock_list_ul_class').value = 'new-list extra-class';
    ui.input('field_alphablock_list_ul_class').dispatchEvent(new ui.dom.window.Event('input', { bubbles: true }));
    await delay(180);
    setMode(ui, 'card'); await delay(180);
    assert.equal(ui.json().show_as, 'card');
    assert.equal(ui.json().list_ul_class, 'new-list extra-class');
    assert.equal(ui.json().posts_per_row, -1);
    assert.equal(ui.input('field_layout').value, 'Carousel');
    setMode(ui, 'list'); await delay(180);
    assert.equal(ui.input('field_alphablock_list_ul_class').value, 'new-list extra-class');
  } finally { ui.dom.window.close(); }
});
test('reopening List retains hidden card values even when ACF controls have reset', async () => {
  const ui = editor({ show_as: 'list', card: 12, posts_per_row: -1, layout: 'Carousel', mobile_layout: 'Swipe', show_links: ['Show All'], list_icon: 'fa-solid fa-chevron-right' }, { show_as: 'card', card: null, posts_per_row: 3, layout: 'Wrap', mobile_layout: 'Wrap', show_links: [] });
  try {
    await delay(380);
    assert.equal(ui.json().show_as, 'list');
    assert.equal(ui.json().card, 12);
    assert.equal(ui.json().layout, 'Carousel');
    setMode(ui, 'card'); await delay(180);
    assert.equal(ui.input('field_card').value, '12');
    assert.equal(ui.input('field_posts_per_row').value, '-1');
    assert.equal(ui.input('field_layout').value, 'Carousel');
    assert.equal(ui.json().show_links[0], 'Show All');
    assert.equal(ui.json().list_icon, 'fa-solid fa-chevron-right');
  } finally { ui.dom.window.close(); }
});
test('JSON edits set the toggle and list settings without losing inactive values', async () => {
  const ui = editor({ show_as: 'card', card: 12, layout: 'Carousel' });
  try {
    await delay(380);
    ui.input('field_json').value = JSON.stringify({ ...ui.json(), show_as: 'list', list_ul_class: 'links', list_li_class: 'item', list_a_class: 'link', list_icon: 'fa-solid fa-check' });
    ui.input('field_json').dispatchEvent(new ui.dom.window.Event('input', { bubbles: true }));
    await delay(480);
    ui.input('field_json').dispatchEvent(new ui.dom.window.Event('blur'));
    assert.equal(ui.json().show_as, 'list');
    assert.equal(ui.input('field_alphablock_list_icon').value, 'fa-solid fa-check');
    assert.equal(ui.json().card, 12);
    assert.equal(ui.json().layout, 'Carousel');
    ui.input('field_alphablock_list_icon').value = '';
    ui.input('field_alphablock_list_icon').dispatchEvent(new ui.dom.window.Event('input', { bubbles: true }));
    await delay(180);
    assert.equal(ui.json().list_icon, '');
  } finally { ui.dom.window.close(); }
});
test('a partial file-by-file deployment preserves new JSON values if the new controls are absent', async () => {
  const ui = editor({ show_as: 'list', list_ul_class: 'keep-this', list_icon: 'fa-solid fa-check' });
  try {
    ui.dom.window.document.querySelectorAll('.acf-field[data-key="field_alphablock_show_as"], .acf-field[data-key^="field_alphablock_list_"]').forEach(field => field.remove());
    await delay(380);
    assert.equal(ui.json().show_as, 'list');
    assert.equal(ui.json().list_ul_class, 'keep-this');
    assert.equal(ui.json().list_icon, 'fa-solid fa-check');
  } finally { ui.dom.window.close(); }
});

test('Select2-style jQuery changes update JSON and survive a mode switch', async () => {
  const ui = editor({ show_as: 'card', card: 12 }, {}, { jQuery: true });
  try {
    await delay(380);
    const select = ui.input('field_card');
    ui.dom.window.jQuery(select).append(new ui.dom.window.Option('Other card', '13')).val('13').trigger('change');
    await delay(180);
    assert.equal(ui.json().card, 13);
    setMode(ui, 'list'); await delay(180);
    assert.equal(ui.json().card, 13);
    ui.dom.window.jQuery(ui.input('field_alphablock_list_ul_class')).val('jquery-list').trigger('change');
    await delay(180);
    assert.equal(ui.json().list_ul_class, 'jquery-list');
    setMode(ui, 'card'); await delay(180);
    assert.equal(ui.input('field_card').value, '13');
    assert.equal(ui.json().card, 13);
  } finally { ui.dom.window.close(); }
});
