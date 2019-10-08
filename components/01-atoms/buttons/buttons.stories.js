import React from 'react';
import { storiesOf } from '@storybook/react';

import Button from './react/Button.component';

import button from './twig/button.twig'
const buttonTwig = (
  button({ button_content: "Twig Button" })
)

const buttonAlt = (
  button({
    button_content: "Button Alternative",
    button_modifiers: ['alt']
  })
)

const buttonAlt2 = (
  button({
    button_content: "Button Alternative",
    button_modifiers: ['alt-2']
  })
)

/**
 * Add storybook definition for Buttons.
 */
storiesOf('Atoms/Buttons', module)
  .add('React button', () => (
    <Button>React Button</Button>
  ))
  .add('Twig button', () =>
    <div dangerouslySetInnerHTML={{__html: buttonTwig}}></div>
  )
  .add('Button Alternative', () =>
    <div dangerouslySetInnerHTML={{__html: buttonAlt}}></div>
  )
  .add('Button Alternative 2', () =>
    <div dangerouslySetInnerHTML={{__html: buttonAlt2}}></div>
  )
