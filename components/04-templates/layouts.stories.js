import React from 'react';
import { storiesOf } from '@storybook/react';

import fullWidth from './full-width.twig';
import withSidebar from './with-sidebar.twig';

const fullWidthData = (
  fullWidth({
    social_menu__items: {
      1: {
        title: 'Twitter',
        url: '#',
        icon: 'twitter'
      },
      2: {
        title: 'Facebook',
        url: '#',
        icon: 'facebook'
      },
      3: {
        title: 'Instagram',
        url: '#',
        icon: 'instagram'
      }
    },
    footer_menu__items: {
      1: {
        title: 'Item 1',
        url: '#'
      },
      2: {
        title: 'Item 2',
        url: '#'
      },
      3: {
        title: 'Item 3',
        url: '#'
      }
    }
  })
)

const withSidebarData = (
  withSidebar({
    social_menu__items: {
      1: {
        title: 'Twitter',
        url: '#',
        icon: 'twitter'
      },
      2: {
        title: 'Facebook',
        url: '#',
        icon: 'facebook'
      },
      3: {
        title: 'Instagram',
        url: '#',
        icon: 'instagram'
      }
    },
    footer_menu__items: {
      1: {
        title: 'Item 1',
        url: '#'
      },
      2: {
        title: 'Item 2',
        url: '#'
      },
      3: {
        title: 'Item 3',
        url: '#'
      }
    }
  })
)

/**
 * Add storybook definition for CTAs.
 */
storiesOf('Templates/Layouts', module)
  .add('Full Width', () =>
    <div dangerouslySetInnerHTML={{__html: fullWidthData}}></div>
  )
  .add('With Sidebar', () =>
    <div dangerouslySetInnerHTML={{__html: withSidebarData}}></div>
  )
