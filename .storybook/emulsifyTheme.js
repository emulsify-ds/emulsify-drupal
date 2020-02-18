// Documentation on theming Storybook: https://storybook.js.org/docs/configurations/theming/

import { create } from '@storybook/theming';

export default create({
  base: 'light',
  // Branding
  brandTitle: 'Emulsify',
  brandUrl: 'https://emulsify.info',
  brandImage:
    'https://raw.githubusercontent.com/emulsify-ds/emulsify-design-system/master/images/logo.png',
});
