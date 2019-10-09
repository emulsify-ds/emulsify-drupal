import React from 'react';
import { storiesOf } from '@storybook/react';

import grid from './grid.twig'

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
 * Add storybook definition for Grids.
 */
storiesOf('Base/Grids', module)
  .add('Default', () => (
    <div dangerouslySetInnerHTML={{__html: Grid }}></div>
  ))
