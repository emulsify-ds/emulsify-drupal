import React from 'react';
import { storiesOf } from '@storybook/react';
import { useEffect } from '@storybook/client-api';

import { globalData } from '../../../.storybook/globalData';

import mainMenu from '../../02-molecules/menus/main-menu/main-menu';

import article from './article.twig';

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
          page_layout_modifier: globalData.page_layout_modifier,
          menu_items: globalData.main_menu,
          breadcrumb: globalData.breadcrumb,
          social_menu__items: globalData.social_menu__items,
          footer_menu__items: globalData.footer_menu__items,
          card__link__text: 'Click here',
        }),
      }}
      />
    );
  });
