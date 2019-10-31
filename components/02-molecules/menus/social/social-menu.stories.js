import React from 'react';
import { storiesOf } from '@storybook/react';

import socialMenu from './social-menu.twig';

import socialMenuData from './social-menu.yml';

/**
 * Add storybook definition for Inline Menus.
 */
storiesOf('Molecules/Menus', module)
  .add('Social', () => <div dangerouslySetInnerHTML={{ __html: socialMenu(socialMenuData) }} />);
