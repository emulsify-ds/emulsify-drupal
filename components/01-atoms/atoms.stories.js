import React from 'react';
import { storiesOf } from '@storybook/react';
import { action } from '@storybook/addon-actions';

import button from './button/button.twig'
const buttonTwig = (
  button({ button_content: "Click Me I'm Twig" })
)

/**
 * Add storybook definition for Button.
 */
storiesOf('Atoms/Button', module)
  .add('button', () => 
    <div dangerouslySetInnerHTML={{__html: buttonTwig}}></div>
  )
