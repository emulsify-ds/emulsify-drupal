import React from 'react';
import { storiesOf } from '@storybook/react';

import { globalData } from '../../../.storybook/globalData';

import home from './home.twig';

/**
 * Add storybook definition for Landing Pages.
 */
storiesOf('Pages/Landing Pages', module)
  .add('Home', () => (
    <div dangerouslySetInnerHTML={{
      __html: home({
        page_layout_modifier: globalData.page_layout_modifier,
        breadcrumb: globalData.breadcrumb,
        social_menu__items: globalData.social_menu__items,
        footer_menu__items: globalData.footer_menu__items,
      }),
    }}
    />
  ));
