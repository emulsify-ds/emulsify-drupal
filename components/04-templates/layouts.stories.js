import React from 'react';
import { storiesOf } from '@storybook/react';

import { social_menu__items, footer_menu__items } from '../../.storybook/globalData';

import fullWidth from './full-width.twig';
import withSidebar from './with-sidebar.twig';

const fullWidthData = fullWidth({ social_menu__items, footer_menu__items });
const withSidebarData = withSidebar({ social_menu__items, footer_menu__items });

/**
 * Add storybook definitions for Templates.
 */
storiesOf('Templates/Layouts', module)
  .add('Full Width', () =>
    <div dangerouslySetInnerHTML={{__html: fullWidthData}}></div>
  )
  .add('With Sidebar', () =>
    <div dangerouslySetInnerHTML={{__html: withSidebarData}}></div>
  )
