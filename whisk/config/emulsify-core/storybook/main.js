// Pass an empty configOverrides by default.
const configOverrides = {};

// Uncomment the following section to override the default Emulsify Core configuration.
// Doing so is a complete override so no configuration from Emulsify Core's main.js will be inherited.
// See https://storybook.js.org/docs/7/configure for details.
// const configOverrides = {
//   stories: [
//     '../../../../components/**/*.stories.@(js|jsx|ts|tsx)',
//   ],
//   addons: [
//     '../../../@storybook/addon-a11y',
//     '../../../@storybook/addon-links',
//     '../../../@storybook/addon-essentials',
//     '../../../@storybook/addon-themes',
//     '../../../@storybook/addon-styling-webpack'
//   ],
//   core: {
//     builder: 'webpack5',
//   },
//   framework: {
//     name: '@storybook/html-webpack5',
//     options: {},
//   },
//   docs: {
//     autodocs: true,
//   },
// };

module.exports = {configOverrides};