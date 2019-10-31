import React from 'react';
import { storiesOf } from '@storybook/react';

import grid from './grid.twig';

import gridData from './grid.yml';
import gridCardData from './grid-cards.yml';
import gridCtaData from './grid-ctas.yml';

/**
 * Add storybook definition for Grids.
 */
storiesOf('Organisms/Grids', module)
  .add('Default', () => (
    <div dangerouslySetInnerHTML={{ __html: grid(gridData) }} />
  ))
  .add('Card Grid', () => (
    <div dangerouslySetInnerHTML={{ __html: grid({ ...gridData, ...gridCardData }) }} />
  ))
  .add('CTA Grid', () => (
    <div dangerouslySetInnerHTML={{ __html: grid({ ...gridData, ...gridCtaData }) }} />
  ));
