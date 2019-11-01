import React from 'react';
import { storiesOf } from '@storybook/react';

import fullWidth from './full-width.twig';
import withSidebar from './with-sidebar.twig';

import mainMenu from '../02-molecules/menus/main-menu/main-menu.yml';
import socialMenu from '../02-molecules/menus/social/social-menu.yml';
import footerMenu from '../02-molecules/menus/inline/inline-menu.yml';

/**
 * Add storybook definitions for Templates.
 */
storiesOf('Templates/Layouts', module)
  .add('Full Width', () => (
    <div dangerouslySetInnerHTML={{
      __html: fullWidth({ ...mainMenu, ...socialMenu, ...footerMenu }),
    }}
    />
  ))
  .add('With Sidebar', () => (
    <div dangerouslySetInnerHTML={{
      __html: withSidebar({ ...mainMenu, ...socialMenu, ...footerMenu }),
    }}
    />
  ));
