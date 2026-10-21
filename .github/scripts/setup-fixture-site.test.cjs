const assert = require('node:assert/strict');
const fs = require('node:fs');
const os = require('node:os');
const path = require('node:path');
const { spawnSync } = require('node:child_process');
const test = require('node:test');

const script = path.join(__dirname, 'setup-fixture-site.sh');

test('sets prerelease fixture stability before the first resolve and keeps stable legs stable', () => {
  const temporary = fs.mkdtempSync(path.join(os.tmpdir(), 'emulsify-fixture-stability-'));
  try {
    const commands = path.join(temporary, 'commands.jsonl');
    const composer = path.join(temporary, 'composer');
    fs.writeFileSync(composer, `#!/usr/bin/env node
const fs = require('node:fs');
const args = process.argv.slice(2);
fs.appendFileSync(process.env.FIXTURE_COMMANDS, JSON.stringify(args) + '\\n');
if (args[0] === 'create-project') fs.mkdirSync(args.at(-1), { recursive: true });
`, { mode: 0o755 });
    fs.writeFileSync(path.join(temporary, 'php'), '#!/usr/bin/env node\nprocess.stdout.write(process.argv.some((arg) => arg.includes("Drupal::VERSION")) ? process.env.FIXTURE_CORE_MAJOR : "^2.2");\n', { mode: 0o755 });
    fs.writeFileSync(path.join(temporary, 'rsync'), '#!/usr/bin/env node\nprocess.exit(37);\n', { mode: 0o755 });

    for (const [constraint, development, coreMajor] of [
      ['11.3.*', false, '11'],
      ['11.4.*', false, '11'],
      ['12.*', false, '12'],
      ['>=12.0.0-beta1 <12.0.0-RC1@beta', true, '12'],
      ['dev-main', true, '12'],
      ['12.x-dev', true, '12'],
    ]) {
      fs.writeFileSync(commands, '');
      const result = spawnSync('bash', [script, constraint, path.join(temporary, 'fixture'), path.join(__dirname, '../..')], {
        encoding: 'utf8',
        env: { ...process.env, COMPOSER_BIN: composer, FIXTURE_COMMANDS: commands, FIXTURE_CORE_MAJOR: coreMajor, PATH: `${temporary}${path.delimiter}${process.env.PATH}` },
      });
      assert.equal(result.status, 37, `${constraint}: fixture must resolve core and reach theme copying: ${result.stderr}`);
      const calls = fs.readFileSync(commands, 'utf8').trim().split('\n').map((line) => JSON.parse(line));
      const updateIndex = calls.findIndex((args) => args[0] === 'update');
      assert(updateIndex >= 0, `${constraint}: fixture must resolve dependencies`);
      for (const [key, value] of [['minimum-stability', 'dev'], ['prefer-stable', 'true']]) {
        const policyIndex = calls.findIndex((args) => args[0] === 'config' && args.includes(key) && args.at(-1) === value);
        assert.equal(policyIndex >= 0, development, `${constraint}: ${key} must be set only for prerelease/dev fixtures`);
        if (development) assert(policyIndex < updateIndex, `${constraint}: ${key} must be set before the first update`);
      }
    }
  }
  finally {
    fs.rmSync(temporary, { recursive: true, force: true });
  }
});
