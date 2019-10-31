import React from 'react';
import { storiesOf } from '@storybook/react';

import checkbox from './checkbox/checkbox.twig';
import radio from './radio/radio.twig';
import select from './select/select.twig';
import textfields from './textfields/textfields.twig';

import checkboxes from './checkbox/checkbox.yml';
import radios from './radio/radio.yml';
import selectOptions from './select/select.yml';

/**
 * Add storybook definition for Buttons.
 */
storiesOf('Atoms/Forms', module)
  .add('Checkboxes', () => (
    <div dangerouslySetInnerHTML={{ __html: checkbox(checkboxes) }} />
  ))
  .add('Radio Buttons', () => (
    <div dangerouslySetInnerHTML={{ __html: radio(radios) }} />
  ))
  .add('Select Dropdown', () => (
    <div dangerouslySetInnerHTML={{ __html: select(selectOptions) }} />
  ))
  .add('Textfields', () => (
    <div dangerouslySetInnerHTML={{ __html: textfields({}) }} />
  ));
