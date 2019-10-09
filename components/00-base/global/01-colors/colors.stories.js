import React from 'react';
import { storiesOf } from '@storybook/react';

import colors from './colors.twig'
const Grayscale = (
  colors({
    items: {
      1: {
        name: 'White',
        value: 'white',
      },
      2: {
        name: 'Near White',
        value: 'near-white',
      },
      3: {
        name: 'Lightest Gray',
        value: 'gray-lightest',
      },
      4: {
        name: 'Lighter Gray',
        value: 'gray-lighter',
      },
      5: {
        name: 'Light Gray',
        value: 'gray-light',
      },
      6: {
        name: 'Gray',
        value: 'gray',
      },
      7: {
        name: 'Dark Gray',
        value: 'gray-dark',
      },
      8: {
        name: 'Darker Gray',
        value: 'gray-darker',
      },
      9: {
        name: 'Black',
        value: 'black',
      }
    }
  })
)

const Branding = (
  colors({
    items: {
      1: {
        name: 'Green',
        value: 'green',
      },
      2: {
        name: 'Blue',
        value: 'blue',
      }
    }
  })
)

/**
 * Add storybook definition for Colors.
 */
storiesOf('Base/Colors', module)
  .add('Grayscale', () => (
    <div dangerouslySetInnerHTML={{__html: Grayscale}}></div>
  ))
  .add('Branding', () => (
    <div dangerouslySetInnerHTML={{__html: Branding}}></div>
  ))
