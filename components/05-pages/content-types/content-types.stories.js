import React from 'react';
import { storiesOf } from '@storybook/react';
import { useEffect } from '@storybook/client-api';

import mainMenu from '../../02-molecules/menus/main-menu/main-menu';

import article from './article.twig';

import mainMenuData from '../../02-molecules/menus/main-menu/main-menu.yml';
import breadcrumbData from '../../02-molecules/menus/breadcrumbs/breadcrumbs.yml';
import socialMenuData from '../../02-molecules/menus/social/social-menu.yml';
import footerMenuData from '../../02-molecules/menus/inline/inline-menu.yml';

/**
 * Add storybook definition for Content Types.
 */
storiesOf('Pages/Content Types', module)
  .add('Article', () => {
    useEffect(() => {
      mainMenu();
    }, []);
    return (
      <div dangerouslySetInnerHTML={{
        __html: article({
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
  });
