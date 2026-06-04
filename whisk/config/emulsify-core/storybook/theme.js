import { create } from 'storybook/theming';

// This file controls Storybook's manager UI: sidebar, toolbar, addon panels,
// and branding. It does not style rendered components inside the preview iframe.
//
// To fall back to Emulsify Core's default manager theme, export an empty object:
//
// const storybookTheme = {};
// export default storybookTheme;
//
// See https://storybook.js.org/docs/configure/user-interface/theming.

const storybookTheme = create({
  // Base mode. Use 'dark' or 'light' as Storybook's starting color mode.
  base: 'dark',

  // Brand colors. These are used by links, selected states, and accent UI.
  colorPrimary: '#00405B',
  colorSecondary: '#FCB817',

  // App shell. These style Storybook's chrome, not project components.
  appBg: '#00405B',
  appContentBg: '#00202E',
  appHoverBg: '#005F86',
  appPreviewBg: '#ffffff',
  appBorderColor: '#00405B',
  appBorderRadius: 4,

  // Typography. Load external fonts from manager-head.html if the font is not
  // available through the browser or system font stack.
  fontBase: '"Mona Sans", sans-serif',
  fontCode: 'monospace',

  // Text colors
  textColor: '#FFFFFF',
  textInverseColor: '#00202E',
  textMutedColor: '#E6F5FC',

  // Toolbar
  barTextColor: '#E6F5FC',
  barSelectedColor: '#FCB817',
  barHoverColor: '#FFFFFF',
  barBg: '#00202E',

  // Buttons
  buttonBg: '#E6F5FC',
  buttonBorder: '#00405B',

  // Boolean controls
  booleanBg: '#00141D',
  booleanSelectedBg: '#FCB817',

  // Form colors
  inputBg: '#00141D',
  inputBorder: '#00405B',
  inputTextColor: '#FFFFFF',
  inputBorderRadius: 4,

  // Layout grid
  gridCellSize: 8,

  // Branding. Use a stable public URL or a file served from Storybook staticDirs.
  brandTitle: 'Emulsify',
  brandUrl: 'https://emulsify.info',
  brandImage:
    'https://raw.githubusercontent.com/emulsify-ds/.github/6bd435be881bd820bddfa05d88905efe29176a0a/assets/images/header.png',
  brandTarget: '_blank',

  // Example: use a project asset instead of a remote image.
  //
  // brandImage: '/images/storybook-logo.svg',
});

export default storybookTheme;
