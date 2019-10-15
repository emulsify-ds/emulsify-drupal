import React from 'react';
import { storiesOf } from '@storybook/react';

import checkbox from './checkbox/checkbox.twig';

import radio from './radio/radio.twig';

import select from './select/select.twig';

import textfields from './textfields/textfields.twig';

const checkboxTwig = (
  checkbox({
    checkboxes: {
      1: {
        title: 'Option 1',
        checked: 'checked',
      },
      2: {
        title: 'Option 2',
      },
      3: {
        title: 'Option 3',
      },
      4: {
        title: 'Option 4',
      },
    },
  })
);
const radioTwig = (
  radio({
    radios: {
      1: {
        title: 'Option 1',
        checked: 'checked',
      },
      2: {
        title: 'Option 2',
      },
      3: {
        title: 'Option 3',
      },
      4: {
        title: 'Option 4',
      },
    },
  })
);
const selectTwig = (
  select({
    select: {
      0: {
        title: 'Choose an Option',
      },
      1: {
        title: 'Option 1',
      },
      2: {
        title: 'Option 2',
      },
      3: {
        title: 'Option 3',
      },
      4: {
        title: 'Option 4',
      },
    },
  })
);

/**
 * Add storybook definition for Buttons.
 */
storiesOf('Atoms/Forms', module)
  .add('Checkboxes', () => (
    <div dangerouslySetInnerHTML={{ __html: checkboxTwig }} />
  ))
  .add('Radio Buttons', () => (
    <div dangerouslySetInnerHTML={{ __html: radioTwig }} />
  ))
  .add('Select Dropdown', () => (
    <div dangerouslySetInnerHTML={{ __html: selectTwig }} />
  ))
  .add('Textfields', () => (
    <div dangerouslySetInnerHTML={{ __html: textfields({}) }} />
  ));
