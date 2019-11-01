import React from 'react';
import { storiesOf } from '@storybook/react';

import image from './responsive-image.twig';
import figure from './figure.twig';

import imageData from './image.yml';

import figureData from './figure.yml';

/**
 * Add storybook definition for images.
 */
storiesOf('Atoms/Images', module)
  .add('Images', () => (
    <div dangerouslySetInnerHTML={{ __html: image(imageData) }} />
  ))
  .add('Figure', () => (
    <div dangerouslySetInnerHTML={{ __html: figure(figureData) }} />
  ));
