import React from 'react';
import { storiesOf } from '@storybook/react';

import animations from './animations.twig'

/**
 * Add storybook definition for Animations.
 */
storiesOf('Base/Animations', module)
  .add('Fade', () => (
    <div dangerouslySetInnerHTML={{__html: animations({ animation_name: 'Fade' }) }}></div>
  ))
