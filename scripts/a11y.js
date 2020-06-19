#!/usr/bin/env node
/**
 * @file a11y.js
 * Contains a script that, when executed, will execute a11y linting tools
 * against the storybook build.
 */

const R = require('ramda');
const path = require('path');
const pa11y = require('pa11y');
const chalk = require('chalk');
const {
  storybookBuildDir,
  pa11y: pa11yConfig,
  ignore,
  components,
} = require('../a11y.config.js');

const STORYBOOK_BUILD_DIR = path.resolve(__dirname, '../', storybookBuildDir);
const STORYBOOK_IFRAME = path.join(STORYBOOK_BUILD_DIR, 'iframe.html');

const severityToColor = R.cond([
  [R.equals('error'), R.always('red')],
  [R.equals('warning'), R.always('yellow')],
  [R.equals('notice'), R.always('blue')],
]);

const issueIsValid = ({ code, runnerExtras: { description } }) =>
  ignore.codes.includes(code) || ignore.descriptions.includes(description)
    ? false
    : true;

const logIssue = ({ type: severity, message, context, selector }) => {
  console.log(`
    severity: ${chalk[severityToColor(severity)](severity)}
    message: ${message}
    context: ${context}
    selector: ${selector}
  `);
};

const logReport = ({ issues, pageUrl }) => {
  const validIssues = issues.filter(issueIsValid);
  const hasIssues = validIssues.length > 0;

  if (hasIssues) {
    console.log(chalk.red(`Issues found in component: ${pageUrl}`));
    validIssues.map(logIssue);
  } else {
    console.log(chalk.green(`No issues found in component: ${pageUrl}`));
  }

  return hasIssues;
};

const lintComponent = async (name) =>
  pa11y(`${STORYBOOK_IFRAME}?id=${name}`, {
    includeNotices: true,
    includeWarnings: true,
    runners: ['axe'],
    ...pa11yConfig,
  });

const lintReportAndExit = R.pipe(
  R.map(lintComponent),
  (p) => Promise.all(p),
  R.andThen(
    R.pipe(
      R.map(logReport),
      R.reject(R.equals(false)),
      R.unless(R.isEmpty, () => process.exit(1)),
    ),
  ),
);

// Only perform linting/reporting when instructed.
/* istanbul ignore next */
if (R.pathEq(['argv', 2], '-r')(process)) {
  lintReportAndExit(components);
}

module.exports = {
  severityToColor,
  issueIsValid,
  logIssue,
  logReport,
  lintComponent,
  lintReportAndExit,
};
