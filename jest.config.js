module.exports = {
  testEnvironment: 'jsdom',
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
  testPathIgnorePatterns: [
    '<rootDir>/dist',
    '<rootDir>/vendor',
    '<rootDir>/.out',
  ],
};
