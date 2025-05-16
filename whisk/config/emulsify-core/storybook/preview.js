// export global parameters as config overrides.
// This is useful for reorganizing your stories.
// See https://storybook.js.org/docs/writing-stories/parameters#story-parameters.
const overrideParams = {
  actions: { argTypesRegex: '^on[A-Z].*' },
  layout: 'fullscreen',
  options: {
    storySort: {
      method: 'alphabetical',
      order: [
        'Docs',
        ['Intro', '*'],
        'Tokens',
        ['Readme', '*'],
        'Components',
        ['Readme', '*'],
        'Layout',
        ['Readme', '*'],
      ],
      locales: 'en-US',
    },
  },
};

export default overrideParams;