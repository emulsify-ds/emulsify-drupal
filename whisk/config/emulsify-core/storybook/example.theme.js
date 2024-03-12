// Documentation on theming Storybook: https://storybook.js.org/docs/configurations/theming/
import { create } from '@storybook/theming';

export default create({
  base: 'dark',

  // UI
  appBg: '#0000ff',
  appContentBg: '#eee',
  appBorderColor: '#ff0000',
  appBorderRadius: 4,

  // Typography
  fontBase: '"Mona Sans", sans-serif',
  fontCode: 'monospace',

  // Text colors
  textColor: 'white',
  textInverseColor: 'rgba(255,255,255,0.9)',
  textMutedColor: '#E6F5FC',

  // Toolbar default and active colors
  barTextColor: '#0000ff',
  barSelectedColor: '#eeff00',
  barBg: '#eeff00',

  // Form colors
  inputBg: 'red',
  inputBorder: 'silver',
  inputTextColor: '#dddddd',
  inputBorderRadius: 4,
  // Branding
  brandTitle: 'Emulsify',
  brandUrl: 'https://emulsify.info',
  brandImage:
    'https://raw.githubusercontent.com/fourkitchens/emulsify-core/main/assets/images/emulsify-logo-sb.svg?token=GHSAT0AAAAAACIEXLVC5R3KBCX6HGKGTBBSZNYFWMA',
});
