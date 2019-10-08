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

import paragraph from './02-text/text/03-inline-elements.twig'

import blockquote from './02-text/text/02-blockquote.twig'
const blockquoteComponent = (
  blockquote({
    blockquote_content: "Lorem ipsum dolor sit amet, consetetur sadipscing elitr, sed diam nonumy eirmod tempor invidunt ut labore et dolore magna aliquyam erat, sed diam voluptua. At vero eos et accusam et justo duo dolores et ea rebum. Stet clita kasd gubergren, no sea takimata sanctus est Lorem ipsum dolor sit amet. Lorem ipsum dolor sit amet, consetetur sadipscing elitr, sed diam nonumy eirmod tempor invidunt ut labore et dolore magna aliquyam erat, sed diam voluptua. At vero eos et accusam et justo duo dolores et ea rebum. Stet clita kasd gubergren, no sea takimata sanctus est Lorem ipsum dolor sit amet. Lorem ipsum dolor sit amet, consetetur sadipscing elitr, sed diam nonumy eirmod tempor invidunt ut labore et dolore magna aliquyam erat, sed diam voluptua. At vero eos et accusam et justo duo dolores et ea rebum. Stet clita kasd gubergren, no sea takimata sanctus est Lorem ipsum dolor sit amet."
  })
)

import pre from './02-text/text/05-pre.twig'

/**
 * Add storybook definition for Links.
 */
storiesOf('Atoms/Text', module)
  .add('Various', () => (
    <div dangerouslySetInnerHTML={{__html: paragraph({})}}></div>
  ))
  .add('Blockquote', () => (
    <div dangerouslySetInnerHTML={{__html: blockquoteComponent}}></div>
  ))
  .add('Preformatted', () => (
    <div dangerouslySetInnerHTML={{__html: pre({})}}></div>
  ))


import Button from './buttons/react/Button.component';

import button from './buttons/twig/button.twig'
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
