import React from 'react';
import { storiesOf } from '@storybook/react';

import link from './01-links/link/link.twig'
const linkComponent = (
  link({
    link_attributes: {
      target: '_blank'
    },
    link_url: 'https://github.com/fourkitchens/gatsby-starter-emulsify-drupal',
    link_content: "This is my link text"
  })
)

/**
 * Add storybook definition for Links.
 */
storiesOf('Atoms/Links', module)
  .add('Links', () => (
    <div dangerouslySetInnerHTML={{__html: linkComponent}}></div>
  ))


import headings from './02-text/00-headings/headings.twig'
  
/**
 * Add storybook definition for Links.
 */
storiesOf('Atoms/Headings', module)
  .add('Headings', () => (
    <div dangerouslySetInnerHTML={{__html: headings({})}}></div>
  ))


import Button from './06-buttons/react/Button.component';

import button from './06-buttons/twig/button.twig'
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
 * Add storybook definition for Button.
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
