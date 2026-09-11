const assert = require('node:assert/strict');
const fs = require('node:fs');
const os = require('node:os');
const path = require('node:path');
const { spawnSync } = require('node:child_process');
const test = require('node:test');

function fixture(t) {
  const directory = fs.mkdtempSync(path.join(os.tmpdir(), 'emulsify-template-parity-'));
  t.after(() => fs.rmSync(directory, { recursive: true, force: true }));
  const write = (relativePath, contents) => {
    const file = path.join(directory, relativePath);
    fs.mkdirSync(path.dirname(file), { recursive: true });
    fs.writeFileSync(file, contents);
  };
  write('drupal/web/core/themes/stable9/templates/layout/page.html.twig', 'Stable9 page\n');
  write('repo/templates/layout/page.html.twig', 'Emulsify page\n');
  write('repo/emulsify.info.yml', 'base theme: false\n');
  return {
    write,
    run: () => spawnSync('bash', [
      path.join(__dirname, 'template-parity.sh'),
      path.join(directory, 'drupal'),
      path.join(directory, 'repo'),
    ], { encoding: 'utf8', env: { ...process.env, TMPDIR: directory } }),
  };
}

test('allows an absent Whisk templates directory and intentional stable9 overrides', (t) => {
  const result = fixture(t).run();
  assert.equal(result.status, 0, result.stderr);
  assert.match(result.stdout, /covers every stable9 template basename/);
});

test('accepts identical Whisk files at the same relative paths, including non-Twig files', (t) => {
  const { write, run } = fixture(t);
  write('repo/whisk/templates/layout/page.html.twig', 'Emulsify page\n');
  write('repo/templates/layout/helper notes.txt', 'Shared helper\n');
  write('repo/whisk/templates/layout/helper notes.txt', 'Shared helper\n');
  const result = run();
  assert.equal(result.status, 0, result.stderr);
});

test('rejects a deliberately changed Whisk file and passes after its bytes are restored', (t) => {
  const { write, run } = fixture(t);
  for (const relativePath of ['layout/page.html.twig', 'layout/helper notes.txt']) {
    write(`repo/templates/${relativePath}`, 'Original contents\n');
    write(`repo/whisk/templates/${relativePath}`, 'Changed contents\n');
    const changed = run();
    assert.notEqual(changed.status, 0);
    assert.ok(changed.stderr.includes(`${relativePath} differs from its parent counterpart`), changed.stderr);
    write(`repo/whisk/templates/${relativePath}`, 'Original contents\n');
    const restored = run();
    assert.equal(restored.status, 0, restored.stderr);
  }
});

test('rejects a Whisk file with no same-path parent even if its basename exists elsewhere', (t) => {
  const { write, run } = fixture(t);
  write('repo/whisk/templates/other/page.html.twig', 'Emulsify page\n');
  const result = run();
  assert.notEqual(result.status, 0);
  assert.match(result.stderr, /other\/page\.html\.twig has no parent counterpart/);
});
