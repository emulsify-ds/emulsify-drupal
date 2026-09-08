export default {
  rootDir: '..',
  testEnvironment: 'jsdom',
  coverageDirectory: '.coverage',
  // Execute the project's native ESM without converting imports to CommonJS.
  transform: {},
  coverageProvider: 'v8',
  collectCoverageFrom: [
    '**/*.{js,mjs,cjs,jsx}',
    '!**/{node_modules,config,dist,.out,.coverage}/**',
    '!**/*.{test,spec,stories}.{js,mjs,cjs,jsx}',
    '!**/__tests__/**',
  ],
};
