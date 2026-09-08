const assert = require('node:assert/strict');
const { spawnSync } = require('node:child_process');
const fs = require('node:fs');
const os = require('node:os');
const path = require('node:path');
const test = require('node:test');

const rootPackage = require('../../package.json');
const starterPackage = require('../../whisk/package.json');

function fixture(t) {
  const dir = fs.mkdtempSync(path.join(os.tmpdir(), 'emulsify-lint-'));
  t.after(() => fs.rmSync(dir, { recursive: true, force: true }));
  return dir;
}

test('PHP lint passes valid files and reports every parse error', (t) => {
  const cwd = fixture(t);
  fs.writeFileSync(path.join(cwd, 'valid file.php'), '<?php echo "valid";\n');
  const run = () => spawnSync('sh', ['-c', rootPackage.scripts['lint:php']], { cwd, encoding: 'utf8' });
  assert.equal(run().status, 0);
  for (const name of ['broken.php', 'also broken.theme']) {
    fs.writeFileSync(path.join(cwd, name), '<?php function {\n');
  }
  const result = run();
  assert.notEqual(result.status, 0);
  const output = result.stdout + result.stderr;
  for (const name of ['valid file.php', 'broken.php', 'also broken.theme']) {
    assert.ok(output.includes(name), output);
  }
});

for (const [wrapper, checks] of Object.entries({
  lint: ['lint-js', 'lint-styles'],
  'lint-fix': ['lint-js', 'lint-styles'],
  format: ['lint-fix', 'prettier-fix'],
})) {
  for (const failures of [[], [0], [1], [0, 1]]) {
    test(`${wrapper} runs every check and propagates failures ${JSON.stringify(failures)}`, (t) => {
      const cwd = fixture(t);
      const scripts = { [wrapper]: starterPackage.scripts[wrapper] };
      fs.writeFileSync(path.join(cwd, 'check.cjs'), 'require("node:fs").appendFileSync("ran", process.argv[2] + "\\n"); process.exit(Number(process.argv[3]));\n');
      checks.forEach((check, index) => {
        scripts[check] = `node check.cjs ${check} ${failures.includes(index) ? 23 + index : 0}`;
      });
      fs.writeFileSync(path.join(cwd, 'package.json'), JSON.stringify({ scripts }));
      const result = spawnSync('npm', ['run', wrapper], { cwd, encoding: 'utf8' });
      assert.equal(result.status === 0, failures.length === 0, result.stdout + result.stderr);
      assert.deepEqual(fs.readFileSync(path.join(cwd, 'ran'), 'utf8').trim().split('\n'), checks);
    });
  }
}
