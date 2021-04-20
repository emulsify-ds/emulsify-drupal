import React from 'react';

import '../../02-molecules/menus/main-menu/main-menu';

import articleTwig from './article.twig';

import mainMenuData from '../../02-molecules/menus/main-menu/main-menu.yml';
import breadcrumbData from '../../02-molecules/menus/breadcrumbs/breadcrumbs.yml';
import socialMenuData from '../../02-molecules/menus/social/social-menu.yml';
import footerMenuData from '../../02-molecules/menus/inline/inline-menu.yml';

/**
 * Storybook Definition.
 */
export default {
  title: 'Pages/Content Types',
  parameters: {
    layout: 'fullscreen',
  },
};

export const article = () => (
  <div
    dangerouslySetInnerHTML={{
      __html: articleTwig({
        page_layout_modifier: 'contained',
        ...mainMenuData,
        ...breadcrumbData,
        ...socialMenuData,
        ...footerMenuData,
        card__link__text: 'Click here',
      }),
    }}
  />
);
