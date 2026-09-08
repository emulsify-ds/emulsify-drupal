# Execution report — September 8, 2026

Implemented the nine requested prompts on the existing `main` branch, starting
from `87016d0`. Work is committed locally. Nothing was pushed, no pull request
was opened, and no release tag was created.

The new accessibility gate fails on three existing Drupal target-size findings.
The Whisk audit retains one unfixed upstream advisory propagated to six package
entries. These are recorded findings, not passing checks.

## Results by prompt

| Prompt | Result and evidence |
| --- | --- |
| 1. Propagate lint failures | 13 regression cases pass: valid/malformed PHP, JavaScript-only and stylesheet-only failures, and running all checks after failures. Real root PHP and Whisk JS/style lint pass. Copied script diffs are in [UPGRADE](../UPGRADE.md). |
| 2. Restrict CI credentials and pin actions | Validation has contents: read; only publishing has the configured GitHub plugin's contents/issues/pull-requests writes. Six action sources, including the added cache action, were verified against upstream release commits. Negative permission/pin checks pass. The authenticated semantic-release dry run passes and predicts patch 7.2.2. Hosted token-permission logs remain unverified. A ci:-only commit does not itself trigger a release. |
| 3. Audit dependency trees | Applied PR #378/#379 lockfile changes and compatible audit fixes. Root audit: zero findings. Clean Whisk install: Core 4.4.0; six high entries from one unresolved extract-zip advisory. Installed Composer audit: zero advisories. No declared range or engine was changed. [Audit findings and deferral](dependency-audit-2026-09-08.md); full output below. |
| 4. Execute generated project tests | The generated theme initially ignored an ESM test and exited 0 with no tests. Updated configuration executes it, fails a broken test, covers tested/untested project JavaScript, and excludes vendored code. An empty theme now exits 1. Interactive twatch detected pass → fail → pass and quit with exit 0. A freshly generated theme installed Core 4.4.0. Temporary probes and the scratch test theme were removed. |
| 5. Repair release/install documentation | Both public package routes verified through registry JSON and actual installations. Composer installed metadata is authoritative; semantic-release-owned npm metadata is retained and guarded. Eight version regressions pass. The draft contains only unreleased changes. PHP and effective Node floors are documented without constraint edits. |
| 6. Render/build/accessibility CI | Real Twig component, Vite/Storybook builds, component tests, existing a11y script, and Drupal route rendering pass. Explicit WCAG 2.2 AA scanning fails the three target-size locations below. Invalid SCSS, missing content, and a new missing-alt image were detected; temporary failures were removed. PR coverage and download caching are configured. [Findings and fixture details](consumer-accessibility.md). |
| 7. Saved favicon previews | Browser/iOS URLs and title agree with saved head output; Android/maskable images and launcher label agree with the saved package. Defaults/full/partial and disabled/missing/source-only cases pass on PHP 8.3/8.5. JavaScript regression confirms unsaved edits preserve previews while diagnostics and dirty-state controls work. Settings and head output remain unchanged. |
| 8. Published Drupal dependencies | Four fresh installs pass: Drupal.org and Packagist routes on PHP 8.3/Drupal 11.4.6 and PHP 8.5/Drupal 12.0.0-alpha1, with Tools 2.2.1. Strict validation/audits pass; both libraries and all six breakpoints register. No stable Drupal 12 exists yet; the fixture explicitly uses the existing dev stability policy with prefer-stable. [Matrix evidence](published-dependency-validation.md). |
| 9. Actual PHP/JS Twig contract | 57 hook assertions pass on each PHP version; 12 shared helper fixtures pass separately on PHP 8.3/8.5 with published Tools 2.2.1 and JavaScript Core 4.4.0. Differences are recorded in [the contract](twig-hook-contract.md) and exact fixtures. Mandatory PHP 8.5/Drupal 11 CI includes test-only Paragraphs. No hook or paired helper behavior changed. |

## Remaining findings and verification limits

- **Accessibility:** `target-size` fails on the site-name link at `/node/1` and
  `/user/login`, and the active Log in tab at `/user/login`. There is no
  allowlist or suppression; the new CI gate stays red until these targets are
  corrected. Exact selectors and dimensions are in the accessibility report.
- **Whisk audit:** `extract-zip <=2.0.1`, GHSA-jmr9-qjv8-65gv, has no available
  fix. Six high package entries lead to that one advisory. No forced override
  or range change was applied.
- **Hosted CI:** browser evidence uses macOS Chrome. The new Linux jobs, hosted
  runtime, and token-permission log blocks have not run on this unpublished
  branch.
- **Full favicon generation:** native PHP has GD but lacks Imagick, so the full
  image-generation/portability smoke suite was not run locally. The new saved
  preview checks passed with real Drupal on PHP 8.3.33 and 8.5.10. CI installs
  Imagick for the existing complete suite.

Final checks include 34 Node regressions, the static release guard, PHP syntax
on 8.3.33/8.5.10, real Whisk lint, strict root Composer validation, and the
generated-component, browser, published-install, hook and paired-helper runs
above. The authenticated semantic-release dry run completed successfully and
selected 7.2.2 without publishing.

Your preexisting `whisk/package.json` edit from Core `^4.3.1` to `^4.4.0` remains
unstaged; the fresh installed tree satisfies it. Your untracked
`docs/release-plan-7.3.0.md` is preserved. Drupal, Tools, engine, library,
breakpoint, region, template, favicon-setting and hook contracts were retained.
Disposable Drupal sites, probe themes and the stale Whisk dependency backup
were removed after validation; recorded evidence and download caches remain.

## Implementation commits and diffstats

The report/documentation follow-up commit is additional to this table.

| Commit | Message | Diffstat |
| --- | --- | --- |
| a55db60 | fix(scripts): propagate every lint and format failure | 4 files changed, 71 insertions(+), 4 deletions(-) |
| 7e8a819 | ci: restrict validation credentials and pin actions | 3 files changed, 55 insertions(+), 22 deletions(-) |
| 278d64b | fix(deps): update audited dependencies and record deferred advisory | 9 files changed, 796 insertions(+), 23 deletions(-) |
| 13ca014 | docs: clarify installation and authoritative release metadata | 3 files changed, 161 insertions(+), 81 deletions(-) |
| 1617e70 | fix(release): reject stale draft versions and metadata drift | 3 files changed, 156 insertions(+), 3 deletions(-) |
| 9609467 | fix(whisk): execute project ESM tests and collect project coverage | 4 files changed, 109 insertions(+), 3 deletions(-) |
| c026d9a | fix(favicon): preview saved generated assets consistently | 7 files changed, 325 insertions(+), 154 deletions(-) |
| 11c0346 | ci: validate published packages on supported Drupal versions | 27 files changed, 1672 insertions(+) |
| 15f10cb | test: add a real generated consumer component fixture | 4 files changed, 74 insertions(+) |
| 01cd98f | ci: audit rendered consumer components and Drupal pages | 6 files changed, 252 insertions(+), 27 deletions(-) |
| c6fac54 | test: characterize PHP and JavaScript Twig contracts | 5 files changed, 819 insertions(+) |
| 9245685 | docs: record Twig contracts and completed maintenance guidance | 8 files changed, 288 insertions(+), 42 deletions(-) |

## Complete final audit outputs

These are the complete captured JSON outputs. The linked audit document explains
the clean installation and the distinction between advisory and network failures.

### Root npm audit — exit 0

```json
{
  "auditReportVersion": 2,
  "vulnerabilities": {},
  "metadata": {
    "vulnerabilities": {
      "info": 0,
      "low": 0,
      "moderate": 0,
      "high": 0,
      "critical": 0,
      "total": 0
    },
    "dependencies": {
      "prod": 1,
      "dev": 514,
      "optional": 25,
      "peer": 23,
      "peerOptional": 0,
      "total": 514
    }
  }
}
```

### Whisk npm audit — exit 1, recorded advisory

```json
{
  "auditReportVersion": 2,
  "vulnerabilities": {
    "@emulsify/core": {
      "name": "@emulsify/core",
      "severity": "high",
      "isDirect": true,
      "via": [
        "pa11y"
      ],
      "effects": [],
      "range": "*",
      "nodes": [
        "node_modules/@emulsify/core"
      ],
      "fixAvailable": false
    },
    "@puppeteer/browsers": {
      "name": "@puppeteer/browsers",
      "severity": "high",
      "isDirect": false,
      "via": [
        "extract-zip"
      ],
      "effects": [
        "puppeteer",
        "puppeteer-core"
      ],
      "range": "<=2.13.2",
      "nodes": [
        "node_modules/@puppeteer/browsers"
      ],
      "fixAvailable": false
    },
    "extract-zip": {
      "name": "extract-zip",
      "severity": "high",
      "isDirect": false,
      "via": [
        {
          "source": 1139346,
          "name": "extract-zip",
          "dependency": "extract-zip",
          "title": "extract-zip unvalidated symlink path traversal",
          "url": "https://github.com/advisories/GHSA-jmr9-qjv8-65gv",
          "severity": "high",
          "cwe": [
            "CWE-22"
          ],
          "cvss": {
            "score": 8.1,
            "vectorString": "CVSS:3.1/AV:N/AC:L/PR:N/UI:R/S:U/C:H/I:H/A:N"
          },
          "range": "<=2.0.1"
        }
      ],
      "effects": [
        "@puppeteer/browsers"
      ],
      "range": "*",
      "nodes": [
        "node_modules/extract-zip"
      ],
      "fixAvailable": false
    },
    "pa11y": {
      "name": "pa11y",
      "severity": "high",
      "isDirect": false,
      "via": [
        "puppeteer"
      ],
      "effects": [
        "@emulsify/core"
      ],
      "range": "7.0.0 - 9.1.1",
      "nodes": [
        "node_modules/pa11y"
      ],
      "fixAvailable": false
    },
    "puppeteer": {
      "name": "puppeteer",
      "severity": "high",
      "isDirect": false,
      "via": [
        "@puppeteer/browsers",
        "puppeteer-core"
      ],
      "effects": [
        "pa11y"
      ],
      "range": "19.8.1 - 24.43.1",
      "nodes": [
        "node_modules/puppeteer"
      ],
      "fixAvailable": false
    },
    "puppeteer-core": {
      "name": "puppeteer-core",
      "severity": "high",
      "isDirect": false,
      "via": [
        "@puppeteer/browsers"
      ],
      "effects": [
        "puppeteer"
      ],
      "range": "19.8.4 - 24.43.1",
      "nodes": [
        "node_modules/puppeteer-core"
      ],
      "fixAvailable": false
    }
  },
  "metadata": {
    "vulnerabilities": {
      "info": 0,
      "low": 0,
      "moderate": 0,
      "high": 6,
      "critical": 0,
      "total": 6
    },
    "dependencies": {
      "prod": 1098,
      "dev": 0,
      "optional": 137,
      "peer": 5,
      "peerOptional": 0,
      "total": 1239
    }
  }
}
```

### Installed Composer audit — exit 0

```json
{
    "advisories": [],
    "abandoned": [],
    "filter": []
}
```
