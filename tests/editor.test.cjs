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
    case 'select':
      inner = '<select>' + (field.allow_null ? '<option value="">Any post type</option>' : '') + Object.entries(field.choices).map(([key, label]) => `<option value="${escape(key)}"${String(value) === key ? ' selected' : ''}>${escape(label)}</option>`).join('') + '</select>'; break;
    case 'post_object': inner = `<select><option value="12" selected>Test card</option></select>`; break;
    case 'checkbox': inner = Object.keys(field.choices).map(key => `<input type="checkbox" value="${escape(key)}"${(value || []).includes(key) ? ' checked' : ''}>`).join(''); break;
    case 'relationship': inner = '<div class="values-list"></div>'; break;
    case 'repeater': inner = '<table><tbody>' + (value || []).map(row => '<tr class="acf-row">' + field.sub_fields.map(sub => `<td>${control(sub, row[sub.name])}</td>`).join('') + '</tr>').join('') + '</tbody></table>'; break;
    default: return '';
  }
  return `<div ${attrs}><div class="acf-input">${inner}</div></div>`;
}
function editor(values, fieldOverrides = {}) {
  const config = { ...base, ...values };
  const form = { ...config, ...fieldOverrides, json: JSON.stringify(config), prompt: 'My saved prompt' };
  const html = '<div class="acf-block-fields" data-block-id="test">' + group.fields.map(field => control(field, form[field.name])).join('') + '</div>';
  const dom = new JSDOM(html, { runScripts: 'outside-only', url: 'https://example.invalid/wp-admin/post.php', virtualConsole: new VirtualConsole() });
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
    assert.equal(current.type, old.type);
    if (old.choices) assert.deepEqual(Object.keys(current.choices).filter(key => key !== 'Post'), Object.keys(old.choices));
  }
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
