const assert = require('node:assert/strict');
const fs = require('node:fs');
const path = require('node:path');
const test = require('node:test');
const vm = require('node:vm');

function element(value = '') {
  return {
    value,
    type: 'text',
    files: [],
    hidden: true,
    textContent: '',
    dataset: {},
    listeners: {},
    style: { values: { '--preview-padding': '0%', '--preview-background': 'transparent' }, setProperty(name, value) { this.values[name] = value; } },
    addEventListener(name, callback) { (this.listeners[name] ??= []).push(callback); },
    dispatch(name) { for (const callback of this.listeners[name] ?? []) callback(); },
    removeAttribute(name) { if (name === 'hidden') this.hidden = false; },
    setAttribute(name) { if (name === 'hidden') this.hidden = true; },
  };
}

function assertPreviewBehavior(behaviorSource) {
  const names = {
    favicon_package_enabled: '1',
    favicon_background_color: '#ffffff',
    favicon_ios_background_color: '#ffffff',
    favicon_ios_padding: '16',
    favicon_ios_icon_name: 'Saved iOS',
    favicon_manifest_short_name: 'Saved Android',
    favicon_manifest_name: 'Saved site',
    favicon_android_background_color: '#ffffff',
    favicon_android_padding: '20',
    'favicon_source_fid[fids]': '1',
    favicon_theme_color: '#ffffff',
    favicon_dark_mode: 'none',
    favicon_dark_background_enabled: '',
    favicon_dark_background_color: '#000000',
    favicon_dark_theme_color_enabled: '',
    favicon_dark_theme_color: '#000000',
    'favicon_dark_source_fid[fids]': '',
    favicon_dark_source_svg: '',
    favicon_dark_source_filename: '',
  };
  const inputs = Object.fromEntries(Object.entries(names).map(([name, value]) => [name, element(value)]));
  inputs.favicon_package_enabled.type = 'checkbox';
  inputs.favicon_package_enabled.checked = true;
  for (const name of ['favicon_dark_background_enabled', 'favicon_dark_theme_color_enabled']) {
    inputs[name].type = 'checkbox';
    inputs[name].checked = false;
  }
  const fileInput = element();
  const darkFileInput = element();
  const notice = element();
  const hint = element();
  const button = element('Regenerate package');
  button.dataset = { defaultLabel: 'Regenerate package', dirtyLabel: 'Regenerate package (changes pending)' };
  const canvases = ['browser', 'ios', 'android', 'maskable'].map(() => element());
  const iosLabel = element();
  iosLabel.textContent = 'Saved iOS';
  const androidLabel = element();
  androidLabel.textContent = 'Saved Android';
  const form = {
    querySelector(selector) {
      if (selector.startsWith('[name="')) return inputs[selector.slice(7, -2)] ?? null;
      if (selector.includes('input[type="file"]')) return selector.includes('favicon_dark_source_fid') ? darkFileInput : fileInput;
      if (selector === '[data-favicon-dirty-state]') return notice;
      if (selector === '[data-favicon-generation-hint]') return hint;
      if (selector === '[data-favicon-regenerate-button]') return button;
      return null;
    },
    querySelectorAll(selector) {
      if (selector.includes('data-preview-canvas')) return canvases;
      if (selector === '[data-preview-label="ios"]') return [iosLabel];
      if (selector === '[data-preview-label="android"]') return [androidLabel];
      if (selector.includes('input[type="file"]')) return [fileInput, darkFileInput];
      return [];
    },
  };
  const snapshot = () => JSON.stringify([canvases.map((canvas) => canvas.style.values), iosLabel.textContent, androidLabel.textContent]);
  const savedPreview = snapshot();
  const Drupal = { behaviors: {} };
  vm.runInNewContext(behaviorSource, { Drupal, once: () => [form] });
  Drupal.behaviors.emulsifyFaviconPreview.attach({});
  assert.equal(snapshot(), savedPreview, 'attaching the behavior must preserve the server-rendered saved output');
  assert.equal(notice.hidden, true);

  inputs.favicon_android_background_color.value = '#123456';
  inputs.favicon_ios_padding.value = '40';
  inputs.favicon_ios_icon_name.value = 'Unsaved iOS';
  inputs.favicon_manifest_short_name.value = 'Unsaved Android';
  inputs.favicon_android_background_color.dispatch('input');
  assert.equal(snapshot(), savedPreview);
  assert.equal(inputs.favicon_theme_color.value, '#123456');
  assert.equal(notice.hidden, false);
  assert.equal(button.value, 'Regenerate package (changes pending)');

  for (const [name, value] of Object.entries(names)) inputs[name].value = value;
  inputs.favicon_android_background_color.dispatch('change');
  assert.equal(notice.hidden, true);
  assert.equal(button.value, 'Regenerate package');
  for (const name of Object.keys(names).filter((name) => name.includes('favicon_dark_'))) {
    const input = inputs[name];
    if (input.type === 'checkbox') input.checked = true;
    else input.value = `${input.value}changed`;
    input.dispatch('change');
    assert.equal(notice.hidden, false, `${name} must show pending changes`);
    assert.equal(snapshot(), savedPreview);
    input.value = names[name];
    if (input.type === 'checkbox') input.checked = false;
    input.dispatch('change');
    assert.equal(notice.hidden, true, `${name} must clear pending changes when restored`);
  }
  darkFileInput.files = [{ name: 'dark.svg' }];
  darkFileInput.dispatch('change');
  assert.equal(notice.hidden, false, 'a pending dark upload must show pending changes');
  assert.equal(snapshot(), savedPreview);
  darkFileInput.files = [];
  darkFileInput.dispatch('change');
  assert.equal(notice.hidden, true);
  fileInput.files = [{ name: 'new.svg' }];
  fileInput.dispatch('change');
  assert.equal(notice.hidden, false);
  assert.equal(hint.hidden, false);
  assert.equal(snapshot(), savedPreview);
}

for (const relativePath of ['assets/admin/favicon-preview.js', 'dist/js/admin/favicon-preview.js']) {
  test(`${relativePath}: unsaved inputs leave saved preview framing and labels intact while helper state updates`, () => {
    assertPreviewBehavior(fs.readFileSync(path.join(__dirname, '../..', relativePath), 'utf8'));
  });
}
