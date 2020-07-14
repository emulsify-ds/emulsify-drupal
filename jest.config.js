module.exports = {
  testEnvironment: 'node',
  coverageDirectory: '.coverage',
  // @TODO: once every file has 100% test coverage,
  // these thresholds should be updated.
  coverageThreshold: {
    global: {
      branches: 0,
      functions: 0,
      lines: 0,
      statements: 0,
    },
  },
};
