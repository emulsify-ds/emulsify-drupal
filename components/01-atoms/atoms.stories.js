import React from 'react';
import { storiesOf } from '@storybook/react';
import { action } from '@storybook/addon-actions';

import Button from './button-react/Button.component';

import button from './button/button.twig'
const buttonTwig = (
  button({ button_content: "Twig Button" })
)

import icons from './images/icons/icons.twig'
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
 * Add storybook definition for Button.
 */
storiesOf('Atoms/Button', module)
  .add('React button', () => (
    <Button onClick={action('button-clicked')}>React Button</Button>
  ))
  .add('Twig button', () => 
    <div dangerouslySetInnerHTML={{__html: buttonTwig}}></div>
  )

/**
 * Add storybook definition for Icon.
 */
storiesOf('Atoms/Icons', module)
  .add('Icons', () => (
    <div dangerouslySetInnerHTML={{__html: iconTwig}}></div>
  ))
