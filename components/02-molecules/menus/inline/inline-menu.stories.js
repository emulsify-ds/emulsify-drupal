import React from 'react';
import { storiesOf } from '@storybook/react';

import menu from './inline-menu.twig'
const menuTwig = (
  menu({
    items: {
      1: {
        title: 'Test',
        url: '#'
      },
      2: {
        title: 'Number 2',
        url: '#'
      }
    }
  })
)

/**
 * Add storybook definition for Inline Menus.
 */
storiesOf('Molecules/Menus', module)
  .add('Inline', () =>
    <div dangerouslySetInnerHTML={{__html: menuTwig}}></div>
  )
