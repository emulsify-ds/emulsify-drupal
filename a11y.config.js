module.exports = {
  storybookBuildDir: '.out',
  pa11y: {
    includeNotices: false,
    includeWarnings: false,
    runners: ['axe'],
  },
  // A11y linting is done on a component-by-component
  // basis, which results in the linter reporting some errors that
  // should be ignored. These codes and descriptions allow for those
  // errors to be targeted specifically.
  ignore: {
    codes: ['landmark-one-main', 'page-has-heading-one'],
    descriptions: ['Ensures all page content is contained by landmarks'],
    stories: [''],
  },
};
