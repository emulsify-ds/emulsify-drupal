import React from 'react';
import { storiesOf } from '@storybook/react';

import socialMenu from './social-menu.twig'
const socialMenuTwig = (
  socialMenu({
    items: {
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
    }
  })
)

/**
 * Add storybook definition for Inline Menus.
 */
storiesOf('Molecules/Menus', module)
  .add('Social', () =>
    <div dangerouslySetInnerHTML={{__html: socialMenuTwig}}></div>
  )
