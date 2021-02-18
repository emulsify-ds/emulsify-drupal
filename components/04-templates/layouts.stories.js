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

export const fullWidth = () =>
  fullWidthTwig({ ...mainMenu, ...socialMenu, ...footerMenu });

export const withSidebar = () =>
  withSidebarTwig({ ...mainMenu, ...socialMenu, ...footerMenu });
