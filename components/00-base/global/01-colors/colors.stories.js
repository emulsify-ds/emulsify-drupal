import React from 'react';
import { storiesOf } from '@storybook/react';

import colors from './colors.twig';

import grayscale from './colors-grayscale.yml';
import branding from './colors-branding.yml';

/**
 * Add storybook definition for Colors.
 */
storiesOf('Base/Colors', module)
  .add('Grayscale', () => (
    <div dangerouslySetInnerHTML={{ __html: colors(grayscale) }} />
  ))
  .add('Branding', () => (
    <div dangerouslySetInnerHTML={{ __html: colors(branding) }} />
  ));
