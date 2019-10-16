import React from 'react';
import { storiesOf } from '@storybook/react';

import { globalData } from '../../.storybook/globalData';

import fullWidth from './full-width.twig';
import withSidebar from './with-sidebar.twig';

/**
 * Add storybook definitions for Templates.
 */
storiesOf('Templates/Layouts', module)
  .add('Full Width', () => (
    <div dangerouslySetInnerHTML={{
      __html: fullWidth({
        menu_items: globalData.main_menu,
        social_menu__items: globalData.social_menu__items,
        footer_menu__items: globalData.footer_menu__items,
      }),
    }}
    />
  ))
  .add('With Sidebar', () => (
    <div dangerouslySetInnerHTML={{
      __html: withSidebar({
        menu_items: globalData.main_menu,
        social_menu__items: globalData.social_menu__items,
        footer_menu__items: globalData.footer_menu__items,
      }),
    }}
    />
  ));
