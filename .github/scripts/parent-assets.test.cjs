const assert = require('node:assert/strict');
const fs = require('node:fs');
const path = require('node:path');
const test = require('node:test');
const yaml = require('js-yaml');

const root = path.resolve(__dirname, '../..');

test('the parent build ships exactly the assets declared by Drupal libraries', () => {
  const libraries = yaml.load(fs.readFileSync(path.join(root, 'emulsify.libraries.yml'), 'utf8'));
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
  const dist = path.join(root, 'dist');
  const emitted = fs.readdirSync(dist, { recursive: true })
    .filter((file) => fs.statSync(path.join(dist, file)).isFile())
    .map((file) => `dist/${file.split(path.sep).join('/')}`);
  assert.deepEqual(emitted.sort(), declared.sort(), 'Do not ship maps, PHP, or undeclared files in the frontend build');
});
