import React from 'react';
import { storiesOf } from '@storybook/react';
import { action } from '@storybook/addon-actions';

import Button from './button-react/Button.component';

import button from './button/button.twig'
const buttonTwig = (
  button({ button_content: "Click Me I'm Twig" })
)

/**
 * Add storybook definition for Button.
 */
storiesOf('Atoms/Button', module)
  .add('React button', () => (
    <Button onClick={action('button-clicked')}>Click Here</Button>
  ))
  .add('Twig button', () => 
    <div dangerouslySetInnerHTML={{__html: buttonTwig}}></div>
  )
