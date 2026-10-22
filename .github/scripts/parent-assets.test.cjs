const assert = require('node:assert/strict');
const fs = require('node:fs');
const path = require('node:path');
const test = require('node:test');
const yaml = require('js-yaml');
const postcss = require('postcss');

const root = path.resolve(__dirname, '../..');

test('the parent build ships exactly the declared libraries and logo', () => {
  const libraries = yaml.load(fs.readFileSync(path.join(root, 'emulsify.libraries.yml'), 'utf8'));
  assert.deepEqual(libraries.oembed_poster.css.theme, { 'dist/css/media/oembed-poster.css': { minified: true } });
  assert.equal(libraries.oembed_poster.js, undefined, 'The iframe poster must work without a JavaScript placeholder');
  const declared = [];
  for (const library of Object.values(libraries)) {
    const assets = { ...Object.assign({}, ...Object.values(library.css ?? {})), ...library.js };
    for (const [file, options] of Object.entries(assets)) {
      assert.match(file, /^dist\/.*\.(css|js)$/);
      assert.equal(options.minified, true, `${file} must declare its production minification`);
      assert(fs.statSync(path.join(root, file)).size > 0, `${file} must ship with the theme`);
      declared.push(file);
    }
  }
  const info = yaml.load(fs.readFileSync(path.join(root, 'emulsify.info.yml'), 'utf8'));
  assert.equal(info.logo, 'assets/images/logo.svg');
  const builtLogo = 'dist/components/images/logo.svg';
  assert.deepEqual(fs.readFileSync(path.join(root, builtLogo)), fs.readFileSync(path.join(root, info.logo)), 'The built logo must match the declared source artwork');
  declared.push(builtLogo);
  const dist = path.join(root, 'dist');
  const emitted = fs.readdirSync(dist, { recursive: true })
    .filter((file) => fs.statSync(path.join(dist, file)).isFile())
    .map((file) => `dist/${file.split(path.sep).join('/')}`);
  assert.deepEqual(emitted.sort(), declared.sort(), 'Do not ship maps, PHP, or undeclared files in the frontend build');
});

test('the built oEmbed poster draws its focus ring above the thumbnail', () => {
  const css = postcss.parse(fs.readFileSync(path.join(root, 'dist/css/media/oembed-poster.css'), 'utf8'));
  const normal = {};
  const forcedColors = {};
  css.walkRules((rule) => {
    const isFocusLayer = rule.selectors.some((selector) => /^:where\(\.emulsify-oembed-poster:focus-visible\)::?after$/.test(selector.replace(/\s+/g, '')));
    if (!isFocusLayer) return;
    let forced = false;
    for (let ancestor = rule.parent; ancestor; ancestor = ancestor.parent) {
      forced ||= ancestor.type === 'atrule' && ancestor.name.toLowerCase() === 'media' && /\(\s*forced-colors\s*:\s*active\s*\)/i.test(ancestor.params);
    }
    if (!forced && rule.parent.type !== 'root') return;
    for (const declaration of rule.nodes.filter((node) => node.type === 'decl')) {
      (forced ? forcedColors : normal)[declaration.prop.toLowerCase()] = declaration.value.trim();
    }
  });
  assert.equal(normal.position, 'absolute', 'The focus indicator must be a separate positioned ::after layer');
  assert.match(normal.content ?? '', /^(?:""|'')$/, 'The focus pseudo-element must be generated');
  assert.equal(normal.inset, '3px');
  assert.equal(normal['pointer-events'], 'none', 'The focus layer must not intercept the play link');
  const border = postcss.list.space((normal.border ?? '').toLowerCase());
  assert(border.includes('3px') && border.includes('solid'), 'The focus layer must have a solid three-pixel border');
  const compactColor = (value) => value.toLowerCase().replace(/\s+/g, '');
  assert(border.some((part) => ['white', '#fff', '#ffffff', 'rgb(255,255,255)', 'rgb(255255255)', 'rgba(255,255,255,1)', 'rgb(255255255/1)'].includes(compactColor(part))), 'The focus border must remain white');
  const shadows = postcss.list.comma(normal['box-shadow'] ?? '');
  assert.equal(shadows.length, 2, 'The border needs dark contrast on both sides');
  assert.equal(shadows.filter((shadow) => /\binset\b/i.test(shadow)).length, 1, 'One contrast shadow must sit inside the border');
  for (const shadow of shadows) {
    const parts = postcss.list.space(shadow.toLowerCase());
    const lengths = parts.filter((part) => /^(?:0(?:px)?|3px)$/.test(part)).map((part) => part === '0px' ? '0' : part);
    assert.deepEqual(lengths, ['0', '0', '0', '3px'], 'Each contrast shadow must surround the full border');
    assert(parts.some((part) => ['#111', '#111111', 'rgb(17,17,17)', 'rgb(171717)', 'rgba(17,17,17,1)', 'rgb(171717/1)'].includes(compactColor(part))), 'Both contrast shadows must remain dark');
  }
  assert.equal(forcedColors['border-color']?.toLowerCase(), 'highlight', 'Forced colors must expose the same focus layer with the system highlight color');
  assert.equal(forcedColors['box-shadow']?.toLowerCase(), 'none', 'Forced colors must not rely on shadows');
});
