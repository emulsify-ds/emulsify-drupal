import React from 'react';
import { storiesOf } from '@storybook/react';

import menu from './inline-menu.twig';

import menuData from './inline-menu.yml';

/**
 * Add storybook definition for Inline Menus.
 */
storiesOf('Molecules/Menus', module)
  .add('Inline', () => <div dangerouslySetInnerHTML={{ __html: menu(menuData) }} />);
