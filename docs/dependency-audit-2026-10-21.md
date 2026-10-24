# Dependency audit — October 21, 2026

The targeted root lockfile update reduces the full npm audit from 66 to 65
vulnerable package entries: **5 critical, 38 high and 22 moderate** remain.
The root runtime-only audit reports zero findings because its tooling is
installed as development dependencies. A fresh Whisk installation resolves
Emulsify Core 4.5.0 and reports **44 entries: 2 critical, 23 high and 19
moderate**. The critical consumer findings are simple-git and its argv parser,
reached through Emulsify CLI; they are additional to the browser extraction
findings recorded in the [September 8 audit](./dependency-audit-2026-09-08.md).

The isolated Composer install and strict metadata validation succeed, but the
Composer audit is **unavailable**: all three attempts exit 100 because Drupal’s
advisory endpoint returns HTTP 503. This record makes no claim about Composer
advisory or abandoned-package counts. All results below are point-in-time
observations, not promises about future registry resolutions.

## Changes and preserved constraints

The update covers the packages raised by
[PR #384](https://github.com/emulsify-ds/emulsify-drupal/pull/384) and
[PR #383](https://github.com/emulsify-ds/emulsify-drupal/pull/383) against main.
The current compatible resolution is:

| Lockfile entry | Before → after | Installed parent’s declared range |
| --- | --- | --- |
| handlebars | 4.7.9 → 4.7.10 | conventional-changelog-writer 8.4.0: `^4.7.7` |
| @actions/http-client’s undici | 6.28.0 → 6.29.1 | @actions/http-client 4.0.1: `^6.23.0` |
| root undici | 7.29.0 → 7.30.1 | @semantic-release/github 12.0.9: `^7.0.0` |

Exactly those three package entries changed. No direct dependency range,
engine field, Drupal/PHP/Tools constraint, override or package version changed.
The root version remains release-owned tooling metadata. Whisk’s existing
glob, Locutus and minimatch overrides were preserved. No lockfile was added to
Whisk, and its in-repository installed dependency tree was left untouched.

The separate npm package used by Semantic Release remains locked at 11.19.1,
including its bundled Undici 6.28.0. It cannot be repaired by updating the
independent Undici entries. A newer compatible npm release already contains
fixed copies of the five affected bundled packages; that separate update was
outside this task. The [bundle inspection](evidence/dependency-audit-2026-10-21/upstream-npm-bundle.json)
records exact versions and tarball hashes. It checked those five packages
against this audit’s ranges; it did not audit the entire newer npm tree.

## Remaining findings and deferrals

npm counts vulnerable **package entries**, including propagation to parent
packages. These are not 65 independent root vulnerabilities: the root output
contains 34 distinct advisory IDs on 17 causal package names. Whisk’s 44 entries
come from nine advisory IDs on the first six package names below. All Whisk
entries also appear in the root audit, but installed versions can differ.

### Fresh Whisk and shared root findings

| Advisory package and IDs | Installed dependency path | Upstream release or follow-up required |
| --- | --- | --- |
| `@simple-git/argv-parser` (critical): [GHSA-v5rq-49vh-5v5c](https://github.com/advisories/GHSA-v5rq-49vh-5v5c) | Core → CLI → simple-git → argv-parser. | **@emulsify/cli** needs a release accepting fixed simple-git 4.x, or **simple-git/argv-parser** need compatible backports. Parser 2.0.1 is already fixed; CLI 2.4.1 still requires simple-git `^3.36.0`. |
| `simple-git` (critical): [GHSA-x6jw-m9v5-85vh](https://github.com/advisories/GHSA-x6jw-m9v5-85vh), [GHSA-g4wm-2vf7-vfgr](https://github.com/advisories/GHSA-g4wm-2vf7-vfgr), [GHSA-858h-whjf-mvg5](https://github.com/advisories/GHSA-858h-whjf-mvg5) | Core 4.5.0 → CLI 2.4.1 → simple-git 3.36.0. | **@emulsify/cli** must adopt the fixed major: simple-git 4.0.2 already exists and uses parser 2.0.1. Updating the current 3.x range cannot select it. |
| `braces` (high): [GHSA-vfj7-8cjw-p6xm](https://github.com/advisories/GHSA-vfj7-8cjw-p6xm) | Core → Stylelint/configs → globby/fast-glob → micromatch → braces 3.0.3. Root Semantic Release and plugins also use micromatch. | **braces** must release a patch, or **micromatch/Stylelint/Semantic Release** must migrate away from the affected implementation. Latest braces 3.0.3 remains affected. |
| `extract-zip` (high): [GHSA-jmr9-qjv8-65gv](https://github.com/advisories/GHSA-jmr9-qjv8-65gv), [GHSA-7pqw-9j4j-h8q3](https://github.com/advisories/GHSA-7pqw-9j4j-h8q3) | Core → Pa11y 9.1.1 → Puppeteer 24.x → @puppeteer/browsers 2.x → extract-zip 2.0.1. | **extract-zip** still needs a patched release; latest remains 2.0.1. Alternatively **@emulsify/core** must adopt Pa11y 10/Puppeteer 25, whose browsers 3.x dependency no longer uses extract-zip. Core currently requires Pa11y `^9.1.1`. |
| `basic-ftp` (high): [GHSA-c475-qrg2-pj4r](https://github.com/advisories/GHSA-c475-qrg2-pj4r) | Core → Pa11y/Puppeteer/browsers → proxy-agent → pac-proxy-agent → get-uri 6.0.5 → basic-ftp 5.x. | **get-uri** must accept fixed basic-ftp 6.2.1 or newer, or **basic-ftp** must backport its fix to 5.x. Installed get-uri requires `^5.0.2`; even latest get-uri 8.0.1 requires a 5.x version. The fixed 6.x release exists but is outside those ranges. A Core/Pa11y browser migration is another upstream route. |
| `sprintf-js` (moderate): [GHSA-hp3w-g68c-fv3c](https://github.com/advisories/GHSA-hp3w-g68c-fv3c) | Core → Jest/Babel/Istanbul → @istanbuljs/load-nyc-config → js-yaml 3 → argparse 1 → sprintf-js 1.0.3. | **sprintf-js** needs a patched release (latest 1.1.3 remains affected), or **@istanbuljs/load-nyc-config** must replace its legacy js-yaml/argparse chain. The moderate finding propagates through Jest coverage packages. |

The findings propagate through Emulsify CLI/Core, the Stylelint configurations,
Puppeteer/Pa11y proxy and extraction dependencies, and the Jest/Babel/Istanbul
coverage packages. The installation evidence lists every affected package,
version and dependency range. Whisk reports 34 entries with
`fixAvailable: true` and ten with `false`. These package-level suggestions do
not establish that every vulnerable leaf has a compatible fix. Fixed Git and
FTP major versions already exist, but their upstream parent constraints block
them.
The upstream packages still needing a leaf fix are **braces**, **extract-zip**
and **sprintf-js**; parent migrations can also remove those dependency paths.

### Additional findings in the committed root tree

The root has 21 additional affected package names. Some are pinned by legacy
parent ranges or npm’s bundled dependencies; others already have compatible
fixes and remain solely because this task updates only Handlebars and Undici.

| Advisory package and IDs | Installed dependency path | Upstream release or follow-up required |
| --- | --- | --- |
| `locutus` (critical): [GHSA-fp25-p6mj-qqg6](https://github.com/advisories/GHSA-fp25-p6mj-qqg6), [GHSA-vh9h-29pq-r5m8](https://github.com/advisories/GHSA-vh9h-29pq-r5m8), [GHSA-vc8f-x9pp-wf5p](https://github.com/advisories/GHSA-vc8f-x9pp-wf5p), [GHSA-4mph-v827-f877](https://github.com/advisories/GHSA-4mph-v827-f877) | Core → twig-drupal-filters → nested Twig 1.17.1 → locutus 2.0.39. | **twig-drupal-filters/Twig 1.x** must publish compatible dependencies that select fixed Locutus 3.x. Locutus 3.0.36 already exists but the installed parent requires `^2.0.11`. Whisk’s existing Locutus override avoids this root-only path; none was added to root. |
| `minimatch` (high): [GHSA-3ppc-4f35-3m26](https://github.com/advisories/GHSA-3ppc-4f35-3m26), [GHSA-7r86-cg39-jmmj](https://github.com/advisories/GHSA-7r86-cg39-jmmj), [GHSA-23c5-xmqv-rm74](https://github.com/advisories/GHSA-23c5-xmqv-rm74) | The same nested Twig 1.17.1 pins minimatch `3.0.x` (installed 3.0.8). | **twig-drupal-filters/Twig 1.x** must widen the range to a patched 3.1.x release. Whisk already overrides `minimatch@3.0.x` to `^3.1.5`; root does not. |
| `brace-expansion` (high): [GHSA-q2hr-2g5m-vwhr](https://github.com/advisories/GHSA-q2hr-2g5m-vwhr), [GHSA-qhr7-859c-m2p7](https://github.com/advisories/GHSA-qhr7-859c-m2p7), [GHSA-6j4f-fj2g-mc7p](https://github.com/advisories/GHSA-6j4f-fj2g-mc7p) | Several ESLint/Twig/BEM/test-exclude minimatch copies, plus npm’s bundled 5.0.9. | Compatible **brace-expansion** fixes already exist (including 1.1.21, 2.1.7 and 5.0.12); independently resolved copies need a separate lock update. The bundled copy belongs to **npm**: compatible npm 11.21.1 bundles 5.0.12. These are scope deferrals, not missing upstream releases. |
| `fast-uri` (moderate): [GHSA-hrr3-gc8f-f4qj](https://github.com/advisories/GHSA-hrr3-gc8f-f4qj) | Root-installed 3.1.7 is below the patched range. | **fast-uri** already has a compatible fix; fresh Whisk resolves 3.1.9. A separate root lock update is needed, outside the two-package task. |
| `http-cache-semantics` (high): [GHSA-ch52-4w7c-c8xp](https://github.com/advisories/GHSA-ch52-4w7c-c8xp) | npm 11.19.1’s bundled 4.2.0. | **npm** owns the bundle update. Compatible npm 11.21.1 already bundles fixed http-cache-semantics 4.3.0; a separate npm lock update is needed. |
| `ip-address` (moderate): [GHSA-rpw4-54j3-4h4q](https://github.com/advisories/GHSA-rpw4-54j3-4h4q), [GHSA-2vr4-cq9g-pvrc](https://github.com/advisories/GHSA-2vr4-cq9g-pvrc), [GHSA-j6r3-76f7-8jcv](https://github.com/advisories/GHSA-j6r3-76f7-8jcv), [GHSA-h3mg-xc3c-68pw](https://github.com/advisories/GHSA-h3mg-xc3c-68pw) | Independent 10.7.0 and npm-bundled 10.5.0 copies. | **ip-address** already has compatible fixed 10.7.4 (resolved by fresh Whisk). **npm** 11.21.1 bundles 10.7.2, outside all recorded affected ranges. Update the independent copy and npm separately. |
| `postcss-selector-parser` (moderate): [GHSA-rj75-hqrm-r3gf](https://github.com/advisories/GHSA-rj75-hqrm-r3gf) | Only npm’s bundled 7.1.4 is reported. | **npm** owns this bundle update; compatible npm 11.21.1 already bundles patched postcss-selector-parser 7.1.6. |
| `shell-quote` (critical): [GHSA-pqg4-j6r4-53mv](https://github.com/advisories/GHSA-pqg4-j6r4-53mv) | Core → concurrently 10.0.5 → exact shell-quote 1.9.0. | **concurrently** has already released a compatible fix: fresh Whisk resolves 10.0.6 with shell-quote 1.12.0. The root needs a separate concurrently lock update; no direct range change is necessary. |
| `source-map-js` (high): [GHSA-68fv-2mgg-jv7q](https://github.com/advisories/GHSA-68fv-2mgg-jv7q) | Root-installed 1.2.1. | **source-map-js** already has compatible fixed 1.2.2, resolved by fresh Whisk. Its root lock update is outside this task. |
| `undici` (high): [GHSA-3wwx-pv8p-q78v](https://github.com/advisories/GHSA-3wwx-pv8p-q78v), [GHSA-r53p-7pc4-xj5r](https://github.com/advisories/GHSA-r53p-7pc4-xj5r), [GHSA-rfgv-xxqx-mfg5](https://github.com/advisories/GHSA-rfgv-xxqx-mfg5) | Only npm 11.19.1 → bundled node-gyp → bundled Undici 6.28.0 remains affected. | **npm** owns the remaining bundled copy. Compatible npm 11.21.1 bundles Undici 6.29.0, outside the recorded ranges. The independently resolved 6.29.1 and 7.30.1 copies updated here are clear of these advisories. |
| `vite` (high): [GHSA-698v-4r3x-3vcv](https://github.com/advisories/GHSA-698v-4r3x-3vcv), [GHSA-r825-w8mm-5pxw](https://github.com/advisories/GHSA-r825-w8mm-5pxw), [GHSA-587h-7wmj-h5pm](https://github.com/advisories/GHSA-587h-7wmj-h5pm) | Root-installed Vite 8.3.0 includes affected launch-editor code. | **vite** already has compatible fixed 8.3.5, resolved by fresh Whisk. A separate root lock update is needed; Core’s existing `^8.3.0` range permits it. |

The additional propagated entries are the seven Semantic Release packages,
concurrently, Twig and twig-drupal-filters. The root’s Locutus/minimatch paths
are absent from fresh Whisk because of the starter’s existing overrides, not
because the legacy Twig dependency ranges have been fixed. No new override,
forced major update, downgrade or dependency replacement was applied. An npm
`fixAvailable` suggestion is not proof of a safe in-range repair: some root
suggestions name older major versions of release plugins.

## Reproduction and full outputs

Root commands ran with Node 24.19.0 / npm 11.17.0 (above the required Node
24.15 minimum):

```sh
npm audit --json
npm update handlebars undici --ignore-scripts
npm ci --ignore-scripts
npm audit --json
npm audit --omit=dev --json
npm run build
git diff --exit-code -- dist
npm run lint
npm run docs:check-commands
npm run release:check -- --skip-smoke
```

The clean install succeeds, built assets remain byte-for-byte unchanged, and
lint, documented-command validation and the static release check pass.
The two full root audits exit 1 for findings; the runtime-only audit exits 0.
Root install versions, range checks and lockfile hashes are in
[root installation evidence](evidence/dependency-audit-2026-10-21/root-installed-versions.json).

For Whisk, copy only the unchanged manifest into an empty directory. Do not copy
an existing lockfile or `node_modules` tree:

```sh
node_bin_dir="$(dirname "$(command -v node)")"
whisk_audit_dir="$(mktemp -d /tmp/emulsify-whisk-audit.XXXXXX)"
cp whisk/package.json "$whisk_audit_dir/package.json"
(
  cd "$whisk_audit_dir"
  export PATH="$node_bin_dir:$PATH"
  node --version
  npm --version
  npm install --ignore-scripts
  npm ls @emulsify/core --depth=0
  npm audit --omit=dev --json
)
```

Use the same Node/npm runtime as the root. Keep the generated lockfile in that
external directory. The [Whisk installation evidence](evidence/dependency-audit-2026-10-21/whisk-installed-versions.json)
records the exact runtime, manifest hash, installed versions, queried upstream
metadata, retained lockfile path and SHA256. Browser lifecycle scripts were
disabled; this audit did not download or run a browser.

The repository still has no Composer lock or vendor tree. Copy its unchanged
manifest into an empty external directory, then run with PHP 8.5.11 and Composer
2.10.3:

```sh
composer_audit_dir="$(mktemp -d /tmp/emulsify-composer-audit.XXXXXX)"
cp composer.json "$composer_audit_dir/composer.json"
(
  cd "$composer_audit_dir"
  COMPOSER_ROOT_VERSION=7.2.2 composer update --no-interaction --no-plugins --no-scripts --prefer-dist
  COMPOSER_ROOT_VERSION=7.2.2 composer audit --no-interaction --no-plugins --format=json
)
composer validate --strict --no-check-publish
```

The isolated install resolves Drupal 11.4.11 and Emulsify Tools 2.2.2 (66
packages). Installation and strict validation exit 0. Each explicit audit
attempt exits 100 after an HTTP 503 from
`https://packages.drupal.org/8/security-advisories`, with empty stdout and no
native audit JSON. The evidence preserves every attempt’s complete stdout,
stderr and exit status in a JSON command-capture envelope; it is not a fabricated
Composer audit response. Advisory sources were not disabled. Rerun the audit
when the endpoint recovers. Plugins and scripts were disabled for this
dependency-only install; no Drupal rendering was attempted.

Complete machine-readable command outputs are tracked without truncation:

| Tree | Exit code | Full output |
| --- | --- | --- |
| Root before updates | 1 (findings) | [Root before](evidence/dependency-audit-2026-10-21/root-npm-before.json) |
| Root after clean install | 1 (findings) | [Root after](evidence/dependency-audit-2026-10-21/root-npm.json) |
| Root, development dependencies omitted | 0 | [Root runtime](evidence/dependency-audit-2026-10-21/root-npm-omit-dev.json) |
| Fresh Whisk, development dependencies omitted | 1 (findings) | [Whisk](evidence/dependency-audit-2026-10-21/whisk-npm.json) |
| Installed Composer tree, three attempts | 100 (service unavailable) | [Composer command captures](evidence/dependency-audit-2026-10-21/composer-audit-attempts.json) |

The [Composer installation evidence](evidence/dependency-audit-2026-10-21/composer-installed-versions.json)
records all resolved versions, commands, metadata validation, manifest and
external lockfile hashes. The September record and its evidence remain
unchanged as historical results.
