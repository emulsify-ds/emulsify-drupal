import React from 'react';
import { storiesOf } from '@storybook/react';

import { globalData } from '../../../.storybook/globalData';

import article from './article.twig';

/**
 * Add storybook definition for Content Types.
 */
storiesOf('Pages/Content Types', module)
  .add('Article', () => (
    <div dangerouslySetInnerHTML={{
      __html: article({
        page_layout_modifier: globalData.page_layout_modifier,
        menu_items: globalData.main_menu,
        breadcrumb: globalData.breadcrumb,
        social_menu__items: globalData.social_menu__items,
        footer_menu__items: globalData.footer_menu__items,
      }),
    }}
    />
  ));
