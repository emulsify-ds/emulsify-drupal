import React from 'react';
import { storiesOf } from '@storybook/react';

import icons from './icons.twig'
const iconTwig = (
  icons({
    items: {
      1: {
        name: "Menu",
        value: "menu"
      },
      2: {
        name: "Twitter",
        value: "twitter"
      }
    }
  })
)

/**
 * Add storybook definition for Icon.
 */
storiesOf('Atoms/Icons', module)
  .add('Icons', () => (
    <div dangerouslySetInnerHTML={{__html: iconTwig}}></div>
  ))
