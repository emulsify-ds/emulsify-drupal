const assert = require('node:assert/strict');
const fs = require('node:fs');
const path = require('node:path');
const postcss = require('postcss');
const sass = require('sass');
const test = require('node:test');
const { JSDOM } = require('jsdom');

const root = path.resolve(__dirname, '../..');
const names = {
  favicon_background_color: '#ffffff',
  favicon_ios_background_color: '#ffffff',
  favicon_ios_padding: '16',
  favicon_ios_icon_name: 'Saved iOS',
  favicon_manifest_short_name: 'Saved Android',
  favicon_manifest_name: 'Saved site',
  favicon_android_background_color: '#ffffff',
  favicon_android_padding: '20',
  favicon_theme_color: '#ffffff',
  favicon_dark_background_color: '#000000',
  favicon_dark_source_svg: '',
  favicon_dark_source_filename: '',
};

function setup(relativePath, css) {
  const { window } = new JSDOM('<style>.form-actions .form-wrapper { display:flex }</style><form class="system-theme-settings"><div class="form-actions"><div class="js-form-wrapper form-wrapper" data-favicon-dirty-state hidden><div class="messages messages--warning" role="status">Changes pending</div></div><input type="submit" data-favicon-regenerate-button value="Regenerate package" data-default-label="Regenerate package" data-dirty-label="Regenerate package (changes pending)" /></div><div data-favicon-generation-hint hidden>Save to generate</div><div id="saved-preview"><span data-preview-canvas="ios" style="--preview-padding:0%"><img src="saved.png" alt="Saved iOS" /></span><span data-preview-label="ios">Saved iOS</span><span data-preview-label="android">Saved Android</span></div></form>', { runScripts: 'outside-only' });
  const { document } = window;
  const style = document.createElement('style');
  style.textContent = css;
  document.head.appendChild(style);
  const form = document.querySelector('form');
  const inputs = {};
  for (const [name, value] of Object.entries(names)) {
    const input = document.createElement('input');
    input.type = name.includes('color') ? 'color' : 'text';
    input.name = name;
    input.value = value;
    form.appendChild(input);
    inputs[name] = input;
  }
  for (const [name, checked] of Object.entries({ favicon_package_enabled: true, favicon_ios_background_custom: false, favicon_android_background_custom: false, favicon_dark_background_enabled: false })) {
    const input = document.createElement('input');
    input.type = 'checkbox';
    input.name = name;
    input.checked = checked;
    form.appendChild(input);
    inputs[name] = input;
  }
  const mode = document.createElement('select');
  mode.name = 'favicon_dark_mode';
  mode.innerHTML = '<option value="none">None</option><option value="invert">Invert</option><option value="alternate">Alternate</option>';
  form.appendChild(mode);
  inputs.favicon_dark_mode = mode;
  for (const prefix of ['favicon_source_fid', 'favicon_dark_source_fid']) {
    const wrapper = document.createElement('div');
    const input = document.createElement('input');
    input.type = 'hidden';
    input.name = `${prefix}[fids]`;
    input.value = prefix === 'favicon_source_fid' ? '1' : '';
    const upload = document.createElement('input');
    upload.type = 'file';
    upload.name = `files[${prefix}]`;
    wrapper.append(input, upload);
    form.appendChild(wrapper);
    inputs[input.name] = input;
    inputs[upload.name] = upload;
  }
  const seen = new WeakSet();
  window.Drupal = { behaviors: {} };
  window.once = (id, elements) => [...elements].filter((element) => {
    if (seen.has(element)) return false;
    seen.add(element);
    return true;
  });
  let jqueryChange;
  window.jQuery = () => ({ on(events, callback) { jqueryChange = callback; } });
  window.eval(fs.readFileSync(path.join(root, relativePath), 'utf8'));
  const attach = (context = document) => window.Drupal.behaviors.emulsifyFaviconPreview.attach(context);
  const change = (name, value) => {
    const input = inputs[name];
    if (input.type === 'checkbox') input.checked = value;
    else input.value = value;
    input.dispatchEvent(new window.Event('input', { bubbles: true }));
    input.dispatchEvent(new window.Event('change', { bubbles: true }));
  };
  return { window, document, form, inputs, attach, change, jqueryChange: () => jqueryChange(), notice: document.querySelector('[data-favicon-dirty-state]') };
}

for (const [relativePath, css] of [
  ['assets/admin/favicon-preview.js', sass.compile(path.join(root, 'assets/admin/favicon-preview.scss')).css],
  ['dist/js/admin/favicon-preview.js', fs.readFileSync(path.join(root, 'dist/css/admin/favicon-preview.css'), 'utf8')],
]) {
  test(`${relativePath}: a saved enabled form hides its warning despite admin form-actions styling`, () => {
    const { window, document, inputs, attach, change, notice } = setup(relativePath, css);
    const preview = document.querySelector('#saved-preview');
    const savedMarkup = preview.outerHTML;
    attach();
    assert.equal(notice.hidden, true);
    assert.equal(window.getComputedStyle(notice).display, 'none', 'Claro/Gin display:flex must not override the hidden warning');
    const button = document.querySelector('[data-favicon-regenerate-button]');
    assert.equal(button.value, 'Regenerate package');
    change('favicon_background_color', '#123456');
    assert.equal(notice.hidden, false);
    assert.equal(window.getComputedStyle(notice).display, 'flex');
    assert.equal(button.value, 'Regenerate package (changes pending)');
    assert.equal(inputs.favicon_ios_background_color.value, '#123456');
    assert.equal(inputs.favicon_android_background_color.value, '#123456');
    assert.equal(inputs.favicon_theme_color.value, '#123456');
    assert.equal(preview.outerHTML, savedMarkup, 'Unsaved inputs must not repaint saved asset previews');
    change('favicon_background_color', '#ffffff');
    assert.equal(window.getComputedStyle(notice).display, 'none');
    assert.equal(button.value, 'Regenerate package');
    attach();
    assert.equal(window.getComputedStyle(notice).display, 'none', 'Repeated behavior attachment must keep a clean form clean');
    window.close();
  });

  test(`${relativePath}: custom colors stay independent while inherited colors follow the browser`, () => {
    const { window, inputs, attach, change } = setup(relativePath, css);
    attach();
    change('favicon_ios_background_custom', true);
    change('favicon_ios_background_color', '#fedcba');
    change('favicon_background_color', '#123456');
    assert.equal(inputs.favicon_ios_background_color.value, '#fedcba');
    assert.equal(inputs.favicon_android_background_color.value, '#123456');
    change('favicon_android_background_custom', true);
    change('favicon_android_background_color', '#abcdef');
    change('favicon_background_color', '#654321');
    assert.equal(inputs.favicon_ios_background_color.value, '#fedcba');
    assert.equal(inputs.favicon_android_background_color.value, '#abcdef');
    assert.equal(inputs.favicon_theme_color.value, '#abcdef');
    change('favicon_ios_background_custom', false);
    change('favicon_android_background_custom', false);
    assert.equal(inputs.favicon_ios_background_color.value, '#654321');
    assert.equal(inputs.favicon_android_background_color.value, '#654321');
    assert.equal(inputs.favicon_theme_color.value, '#654321');
    window.close();
  });

  test(`${relativePath}: jQuery changes and AJAX upload replacements retain the saved baseline`, () => {
    const { window, document, inputs, attach, jqueryChange, notice } = setup(relativePath, css);
    attach();
    inputs.favicon_dark_mode.value = 'invert';
    jqueryChange();
    assert.equal(notice.hidden, false, 'Existing select enhancements can emit jQuery-only events');
    inputs.favicon_dark_mode.value = 'none';
    jqueryChange();
    assert.equal(notice.hidden, true);
    const original = inputs['favicon_source_fid[fids]'];
    const replacement = original.cloneNode();
    replacement.value = '2';
    original.replaceWith(replacement);
    attach(replacement.parentElement);
    assert.equal(notice.hidden, false, 'Managed-file AJAX must compare its new ID with the original saved ID');
    replacement.value = '1';
    replacement.dispatchEvent(new window.Event('change', { bubbles: true }));
    assert.equal(notice.hidden, true, 'Delegated listeners must handle the replacement input');
    const upload = inputs['files[favicon_dark_source_fid]'];
    Object.defineProperty(upload, 'files', { configurable: true, value: [new window.File(['svg'], 'dark.svg')] });
    upload.dispatchEvent(new window.Event('change', { bubbles: true }));
    assert.equal(notice.hidden, false);
    assert.equal(document.querySelector('[data-favicon-regenerate-button]').value, 'Regenerate package (changes pending)');
    window.close();
  });
}

test('built browser preview cards enforce their labeled color schemes', () => {
  const css = postcss.parse(fs.readFileSync(path.join(__dirname, '../../dist/css/admin/favicon-preview.css'), 'utf8'));
  const schemes = {};
  const lightColors = { 'card--light': {}, 'tab--light': {} };
  css.walkRules((rule) => {
    for (const scheme of ['light', 'dark']) {
      if (rule.selectors.includes(`.emulsify-favicon-preview__card--${scheme}`)) {
        rule.walkDecls('color-scheme', (declaration) => { schemes[scheme] = declaration.value; });
      }
    }
    for (const part of Object.keys(lightColors)) {
      if (rule.selectors.includes(`.emulsify-favicon-preview__${part}`)) {
        rule.walkDecls((declaration) => { lightColors[part][declaration.prop] = declaration.value; });
      }
    }
  });
  assert.deepEqual(schemes, { light: 'light', dark: 'dark' }, 'Each browser card must select its color scheme independently of the administrator');
  for (const [part, declarations] of Object.entries(lightColors)) {
    const colors = {
      background: declarations.background ?? declarations['background-color'],
      border: declarations['border-color'] ?? declarations.border,
      color: declarations.color,
    };
    for (const [property, value] of Object.entries(colors)) {
      assert.ok(value, `${part} must set its own ${property}`);
      assert.doesNotMatch(value, /var\(\s*--(?:gin-|emulsify-favicon-preview-surface)/, `${part} ${property} must be independent of the administrator's surface colors`);
    }
  }
});
