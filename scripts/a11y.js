#!/usr/bin/env node
/**
 * @file a11y.js
 * Contains a script that, when executed, will execute a11y linting tools
 * against the storybook build.
 */

const path = require('path');
const pa11y = require('pa11y');
const chalk = require('chalk');
const {
  storybookBuildDir,
  pa11y: pa11yConfig,
  ignore,
} = require('../a11y.config.js');

const STORYBOOK_BUILD_DIR = path.resolve(__dirname, '../', storybookBuildDir);
const STORYBOOK_IFRAME = path.join(STORYBOOK_BUILD_DIR, 'iframe.html');

// @TODO: update this so that it fetches the list dynamically
const getListOfComponents = () => ['molecules-cards--card-example'];

const severityToColor = severity => {
  if (severity === 'error') {
    return 'red';
  }
  if (severity === 'warning') {
    return 'yellow';
  }
  if (severity === 'notice') {
    return 'blue';
  }
};

const lintComponent = async name =>
  pa11y(`${STORYBOOK_IFRAME}?id=${name}`, {
    includeNotices: true,
    includeWarnings: true,
    runners: ['axe'],
    ...pa11yConfig,
  });

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
};

Promise.all(getListOfComponents().map(lintComponent)).then(reports =>
  reports.map(logReport),
);
