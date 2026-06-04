// release.config.js
const parserOpts = {
  breakingHeaderPattern: /^(\w+)(?:\(([^)]*)\))?!: (.+)$/,
  noteKeywords: ['BREAKING CHANGE', 'BREAKING CHANGES', 'BREAKING']
}

module.exports = {
  tagFormat: '${version}',
  branches: ['main'],
  repositoryUrl: 'git@github.com:emulsify-ds/emulsify-drupal.git',
  plugins: [
    [
      '@semantic-release/commit-analyzer',
      {
        preset: 'angular',
        parserOpts
      }
    ],
    [
      '@semantic-release/release-notes-generator',
      {
        preset: 'angular',
        parserOpts,
        writerOpts: {
          commitsSort: ['subject', 'scope']
        }
      }
    ],
    ['@semantic-release/npm', { npmPublish: false }],
    '@semantic-release/github'
  ]
}
