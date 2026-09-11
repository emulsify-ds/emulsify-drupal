#!/usr/bin/env node

// Audit actual browser output using the generated consumer's existing tooling.
// Pa11y's current axe runner includes WCAG 2.1 tags; this additional scan names
// the WCAG 2.2 tags explicitly for both the component and Drupal pages.
const assert = require('node:assert/strict');
const fs = require('node:fs');
const net = require('node:net');
const path = require('node:path');
const { createRequire } = require('node:module');
const { spawn } = require('node:child_process');
const { pathToFileURL } = require('node:url');
const { setTimeout: delay } = require('node:timers/promises');

const wcagTags = ['wcag2a', 'wcag2aa', 'wcag21a', 'wcag21aa', 'wcag22aa'];

function assertAccessible(results, label) {
  assert.equal(results.violations.length, 0, `${label}: ${results.violations.map(({ id, nodes }) => `${id} at ${nodes.map(({ target }) => target.join(' ')).join(', ')}`).join('; ')}`);
}

async function freePort() {
  const server = net.createServer();
  await new Promise((resolve, reject) => {
    server.once('error', reject);
    server.listen(0, '127.0.0.1', resolve);
  });
  const port = server.address().port;
  await new Promise((resolve) => server.close(resolve));
  return port;
}

async function main() {
  const [themeArgument, fixtureArgument, outputArgument, option] = process.argv.slice(2);
  assert(themeArgument && fixtureArgument && outputArgument, 'Usage: rendered-a11y.cjs <generated-theme-dir> <fixture-dir> <output-dir> [--inject-violation]');
  const themeDir = path.resolve(themeArgument);
  const fixtureDir = path.resolve(fixtureArgument);
  const outputDir = path.resolve(outputArgument);
  const requireConsumer = createRequire(path.join(themeDir, 'package.json'));
  const puppeteer = requireConsumer('puppeteer');
  const axePath = requireConsumer.resolve('axe-core/axe.min.js');
  const coreRoot = path.dirname(requireConsumer.resolve('@emulsify/core/package.json'));
  fs.mkdirSync(outputDir, { recursive: true });
  const reports = [];
  let browser;
  let storybook;
  let php;
  const phpLog = fs.openSync(path.join(outputDir, 'a11y-php-server.log'), 'w');

  try {
    process.chdir(themeDir);
    const { startStorybookServer } = await import(pathToFileURL(path.join(coreRoot, 'scripts/a11y.js')).href);
    const index = JSON.parse(fs.readFileSync('.out/index.json', 'utf8'));
    assert(index.entries['consumer-status-panel--default'], 'Storybook must discover the real project component.');
    storybook = await startStorybookServer();
    const port = await freePort();
    const drupalUrl = `http://127.0.0.1:${port}`;
    php = spawn('php', ['-S', `127.0.0.1:${port}`, '.ht.router.php'], {
      cwd: path.join(fixtureDir, 'web'),
      stdio: ['ignore', phpLog, phpLog],
    });
    let ready = false;
    for (let attempt = 0; attempt < 60; attempt += 1) {
      if (php.exitCode !== null) throw new Error('Drupal server exited before rendering a page.');
      try {
        const response = await fetch(`${drupalUrl}/node/1`);
        if (response.ok) { ready = true; break; }
      }
      catch {}
      await delay(250);
    }
    assert(ready, 'Drupal must return a successful page before the accessibility scan.');
    browser = await puppeteer.launch({ headless: true });
    const targets = [
      { label: 'Project Twig component', url: `${storybook.baseUrl}/iframe.html?id=consumer-status-panel--default&viewMode=story`, selector: '.emulsify-smoke', text: 'Your component library is ready' },
      { label: 'Drupal node page', url: `${drupalUrl}/node/1`, selector: 'main.section.main', text: 'Fixture body content for template parity checks.' },
      { label: 'Drupal login form', url: `${drupalUrl}/user/login`, selector: 'main.section.main form', text: 'Username' },
      { label: 'Drupal paged view', url: `${drupalUrl}/node?page=1`, selector: 'main.section.main', text: 'Emulsify fixture page', currentPage: 2 },
      { label: 'Drupal form validation errors', url: `${drupalUrl}/emulsify-fixture/form-errors`, selector: 'main.section.main form', text: 'Fixture validation error for name.', validateForm: true },
    ];
    for (const target of targets) {
      const page = await browser.newPage();
      await page.setViewport({ width: 1280, height: 900 });
      const errors = [];
      page.on('pageerror', (error) => errors.push(error.message));
      try {
        let response = await page.goto(target.url, { waitUntil: 'networkidle0' });
        if (target.validateForm) {
          assert(response && response.ok(), `${target.label} must load before validation.`);
          assert.equal(await page.$$eval('form .form-item--error-message', (elements) => elements.length), 0, 'The fixture form must start without errors.');
          [response] = await Promise.all([
            page.waitForNavigation({ waitUntil: 'networkidle0' }),
            page.click('input[type="submit"][value="Validate fixture"]'),
          ]);
        }
        fs.writeFileSync(path.join(outputDir, `${target.label.toLowerCase().replaceAll(' ', '-')}.html`), await page.content());
        assert(response && response.ok(), `${target.label} must render with a successful response.`);
        await page.waitForSelector(target.selector, { visible: true, timeout: 30000 });
        assert((await page.$eval(target.selector, (element) => element.textContent)).includes(target.text), `${target.label} must contain the expected real rendered content.`);
        if (target.currentPage) {
          const currentPages = await page.$$eval('.pager [aria-current="page"]', (elements) => elements.map((element) => element.textContent.replace(/\s+/g, '')));
          assert.deepEqual(currentPages, [`Page${target.currentPage}`], `${target.label} must identify exactly one current page, page ${target.currentPage}.`);
        }
        if (target.validateForm) {
          const formErrors = await page.evaluate(() => {
            const messages = [...document.querySelectorAll('form .form-item--error-message')];
            const controls = {
              name: ['fixture-name'],
              details: ['fixture-details', 'fixture-details-value'],
              fieldset: ['fixture-fieldset', 'fixture-fieldset-value'],
              date: ['edit-date-date', 'edit-date-time'],
              storage: ['fixture-storage'],
              radios: ['fixture-radios--wrapper', 'edit-radios-first', 'edit-radios-second'],
              checkboxes: ['fixture-checkboxes--wrapper', 'edit-checkboxes-first', 'edit-checkboxes-second'],
            };
            return {
              ids: messages.map((message) => message.id),
              associations: Object.entries(controls).map(([name, ids]) => ids.every((id) => {
                const control = document.getElementById(id);
                const descriptions = control?.getAttribute('aria-describedby')?.split(/\s+/) || [];
                // Composite option help belongs to the fieldset wrapper.
                const needsDescription = !['radios', 'checkboxes'].includes(name) || control?.tagName === 'FIELDSET';
                return descriptions.includes(`fixture-${name}--error`)
                  && (!needsDescription || descriptions.some((description) => description.endsWith('--description')))
                  && descriptions.every((description) => document.getElementById(description));
              })),
              descriptionsResolve: [...document.querySelectorAll('form [aria-describedby]')].every((element) => element.getAttribute('aria-describedby').split(/\s+/).every((id) => document.getElementById(id))),
              detailsSummary: document.querySelector('#fixture-details > summary')?.getAttribute('aria-describedby')?.split(/\s+/).includes('fixture-details--error'),
              radiosClass: Boolean(document.querySelector('#fixture-radios--wrapper .form-radios')),
            };
          });
          assert.deepEqual(formErrors.ids.toSorted(), ['name', 'details', 'fieldset', 'date', 'storage', 'radios', 'checkboxes'].map((name) => `fixture-${name}--error`).sort(), 'All five error template variants, including composite controls, must have unique deterministic ids and the shared error class.');
          assert(formErrors.associations.every(Boolean), 'Every invalid control and group must reference its error while retaining a valid description association.');
          assert(formErrors.descriptionsResolve, 'Every form description reference must resolve to an existing element.');
          assert(formErrors.detailsSummary, 'The focusable details summary must reference its group error.');
          assert(formErrors.radiosClass, 'The radios wrapper must retain the form-radios styling hook.');
        }
        assert.equal(errors.length, 0, `${target.label} browser errors: ${errors.join('; ')}`);
        if (option === '--inject-violation' && target === targets[0]) {
          // Disposable browser DOM only: prove the same gate catches new issues.
          await page.evaluate(() => {
            const image = document.createElement('img');
            image.src = 'data:image/svg+xml,<svg xmlns="http://www.w3.org/2000/svg" width="40" height="40"><rect width="40" height="40"/></svg>';
            document.querySelector('.emulsify-smoke').append(image);
          });
        }
        await page.addScriptTag({ path: axePath });
        const results = await page.evaluate((tags) => axe.run(document, { runOnly: { type: 'tag', values: tags } }), wcagTags);
        reports.push({ label: target.label, url: target.url, ...results });
        for (const violation of results.violations) {
          console.error(`${target.label}: ${violation.id} (${violation.impact}) ${violation.helpUrl}`);
          for (const node of violation.nodes) console.error(`  ${node.target.join(' ')}: ${node.failureSummary}`);
        }
        console.log(`${target.label}: ${results.passes.length} rules passed, ${results.violations.length} violations, ${results.incomplete.length} manual review items.`);
      }
      finally {
        await page.close();
      }
    }
    for (const report of reports) assertAccessible(report, report.label);
  }
  finally {
    fs.writeFileSync(path.join(outputDir, 'wcag22aa.json'), `${JSON.stringify(reports, null, 2)}\n`);
    if (browser) await browser.close();
    if (storybook) await storybook.close();
    if (php) {
      php.kill();
      await new Promise((resolve) => { if (php.exitCode !== null) resolve(); else php.once('exit', resolve); });
    }
    fs.closeSync(phpLog);
  }
}

if (require.main === module) {
  main().catch((error) => { console.error(error.message); process.exitCode = 1; });
}

module.exports = { assertAccessible, wcagTags };
