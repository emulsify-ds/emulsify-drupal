module.exports = {
  hooks: {
    'pre-commit': 'npm run lint-staged ; npm run lint',
    'pre-push': 'npm run lint-staged ; npm run test',
  },
};
