module.exports = {
  tagFormat: '${version}',
  branches: ['master', 'main'],
  repositoryUrl: 'git@github.com:emulsify-ds/emulsify-drupal.git',
  plugins: [
    '@semantic-release/commit-analyzer',
    '@semantic-release/release-notes-generator',
    '@semantic-release/github',
  ],
};
