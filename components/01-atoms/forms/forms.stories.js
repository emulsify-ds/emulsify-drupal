import React from 'react';
import { storiesOf } from '@storybook/react';

import checkbox from './checkbox/checkbox.twig'
const checkboxTwig = (
  checkbox({
    checkboxes: {
      1: {
        title: "Option 1",
        checked: "checked"
      },
      2: {
        title: "Option 2"
      },
      3: {
        title: "Option 3"
      },
      4: {
        title: "Option 4"
      }
    }
  })
)

import radio from './radio/radio.twig'
const radioTwig = (
  radio({
    radios: {
      1: {
        title: "Option 1",
        checked: "checked"
      },
      2: {
        title: "Option 2"
      },
      3: {
        title: "Option 3"
      },
      4: {
        title: "Option 4"
      }
    }
  })
)

/**
 * Add storybook definition for Buttons.
 */
storiesOf('Atoms/Forms', module)
  .add('Checkboxes', () => (
    <div dangerouslySetInnerHTML={{__html: checkboxTwig}}></div>
  ))
  .add('Radio Buttons', () => (
    <div dangerouslySetInnerHTML={{__html: radioTwig}}></div>
  ))
