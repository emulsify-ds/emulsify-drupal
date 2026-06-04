// This file is loaded in Storybook's browser preview iframe through Vite.
//
// Use it for Storybook preview parameters and browser-side imports that stories
// need, such as global CSS. Emulsify Core keeps its default a11y parameters and
// merges this project's parameter overrides into them.
//
// See https://storybook.js.org/docs/writing-stories/parameters#story-parameters.

// Example: load project CSS into every story.
//
// import '../../../src/global/storybook.css';

// Example: override selected Storybook parameters.
//
// export const parameters = {
//   layout: 'fullscreen',
//   backgrounds: {
//     default: 'light',
//     values: [
//       { name: 'light', value: '#ffffff' },
//       { name: 'dark', value: '#00202E' },
//     ],
//   },
//   controls: {
//     expanded: true,
//     matchers: {
//       color: /(background|color)$/i,
//       date: /Date$/i,
//     },
//   },
//   a11y: {
//     config: {
//       detailedReport: true,
//     },
//   },
// };

export const parameters = {};

export default parameters;
