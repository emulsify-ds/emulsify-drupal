import React from 'react';
import { storiesOf } from '@storybook/react';

import paragraph from './03-inline-elements.twig';
import blockquote from './02-blockquote.twig';
import pre from './05-pre.twig';

import blockquoteData from './blockquote.yml'

/**
 * Add storybook definition for Text.
 */
storiesOf('Atoms/Text', module)
  .add('Various', () => (
    <div dangerouslySetInnerHTML={{ __html: paragraph({}) }} />
  ))
  .add('Blockquote', () => (
    <div dangerouslySetInnerHTML={{ __html: blockquote(blockquoteData) }} />
  ))
  .add('Preformatted', () => (
    <div dangerouslySetInnerHTML={{ __html: pre({}) }} />
  ));
