import React from 'react';
import { storiesOf } from '@storybook/react';
import { action } from '@storybook/addon-actions';

import colors from './global/01-colors/colors.twig'
const Default = (
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

const Project = (
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

import animations from './global/animations/animations.twig'

import grid from './layouts/grid/grid.twig'

const Grid = (
  grid({
    grid_label: "Default",
    items: {
      1: "",
      2: "",
      3: ""
    }
  })
)

/**
 * Add storybook definition for Colors.
 */
storiesOf('Base/Colors', module)
  .add('Default', () => (
    <div dangerouslySetInnerHTML={{__html: Default}}></div>
  ))
  .add('Project', () => (
    <div dangerouslySetInnerHTML={{__html: Project}}></div>
  ))

/**
 * Add storybook definition for Animations.
 */
storiesOf('Base/Animations', module)
  .add('Default', () => (
    <div dangerouslySetInnerHTML={{__html: animations({ animation_name: 'Fade' }) }}></div>
  ))

/**
 * Add storybook definition for Grids.
 */
storiesOf('Base/Grids', module)
  .add('Default', () => (
    <div dangerouslySetInnerHTML={{__html: Grid }}></div>
  ))
