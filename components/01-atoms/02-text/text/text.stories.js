import React from 'react';
import { storiesOf } from '@storybook/react';

import paragraph from './03-inline-elements.twig'

import blockquote from './02-blockquote.twig'
const blockquoteComponent = (
  blockquote({
    blockquote_content: "Lorem ipsum dolor sit amet, consetetur sadipscing elitr, sed diam nonumy eirmod tempor invidunt ut labore et dolore magna aliquyam erat, sed diam voluptua. At vero eos et accusam et justo duo dolores et ea rebum. Stet clita kasd gubergren, no sea takimata sanctus est Lorem ipsum dolor sit amet. Lorem ipsum dolor sit amet, consetetur sadipscing elitr, sed diam nonumy eirmod tempor invidunt ut labore et dolore magna aliquyam erat, sed diam voluptua. At vero eos et accusam et justo duo dolores et ea rebum. Stet clita kasd gubergren, no sea takimata sanctus est Lorem ipsum dolor sit amet. Lorem ipsum dolor sit amet, consetetur sadipscing elitr, sed diam nonumy eirmod tempor invidunt ut labore et dolore magna aliquyam erat, sed diam voluptua. At vero eos et accusam et justo duo dolores et ea rebum. Stet clita kasd gubergren, no sea takimata sanctus est Lorem ipsum dolor sit amet."
  })
)

import pre from './05-pre.twig'

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
