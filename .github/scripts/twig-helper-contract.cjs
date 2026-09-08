#!/usr/bin/env node

const assert = require('node:assert/strict');
const fs = require('node:fs');
const path = require('node:path');
const { createRequire } = require('node:module');
const { pathToFileURL } = require('node:url');

async function main() {
  assert.ok(process.argv[2], 'Pass the installed generated theme directory.');
  const requireProject = createRequire(path.resolve(process.argv[2], 'package.json'));
  const core = requireProject('@emulsify/core/package.json');
  const floor = core.version.match(/^4\.(\d+)\.(\d+)/);
  assert.ok(floor && (Number(floor[1]) > 3 || Number(floor[1]) === 3 && Number(floor[2]) >= 1), `Core ${core.version} is below the supported helper baseline.`);
  const { bemAttributes, addAttributes } = await import(pathToFileURL(requireProject.resolve('@emulsify/core/extensions/twig')));
  const cases = require('../fixtures/twig-helper-contract.json');
  const observed = {};
  for (const fixture of cases) {
    const values = structuredClone(fixture.context || {});
    const invocation = { context: { attributes: fixture.contextKind === 'object' ? addAttributes(values) : values } };
    const args = fixture.args;
    const attributes = fixture.helper === 'bem'
      ? bemAttributes(args[0], args[1], args[2], args[3], args[4], invocation)
      : addAttributes(args[0], invocation);
    // Preserve each fixture.divergence: reversed PHP object BEM arguments,
    // JS class sanitization, context clearing, replacement arrays and boolean handling.
    const result = { attributes: attributes.toObject(), html: String(attributes), contextAfter: invocation.context.attributes };
    observed[fixture.id] = result;
    if (!process.argv.includes('--dump')) assert.deepEqual(result, fixture.js, `${fixture.id}: ${fixture.divergence || 'current helper output'}`);
  }
  if (process.argv.includes('--dump')) console.log(JSON.stringify(observed, null, 2));
  else console.log(`PASS ${cases.length} JavaScript Twig helper cases on Core ${core.version}.`);
}

main().catch((error) => { console.error(error); process.exitCode = 1; });
