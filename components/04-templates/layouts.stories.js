import React from 'react';

import fullWidthTwig from './full-width.twig';
import withSidebarTwig from './with-sidebar.twig';

import mainMenu from '../02-molecules/menus/main-menu/main-menu.yml';
import socialMenu from '../02-molecules/menus/social/social-menu.yml';
import footerMenu from '../02-molecules/menus/inline/inline-menu.yml';

/**
 * Storybook Definition.
 */
export default {
  title: 'Templates/Layouts',
  parameters: {
    layout: 'fullscreen',
  },
};

export const fullWidth = () => (
  <div
    dangerouslySetInnerHTML={{
      __html: fullWidthTwig({ ...mainMenu, ...socialMenu, ...footerMenu }),
    }}
  />
);

export const withSidebar = () => (
  <div
    dangerouslySetInnerHTML={{
      __html: withSidebarTwig({ ...mainMenu, ...socialMenu, ...footerMenu }),
    }}
  />
);
