import React from 'react';
import { storiesOf } from '@storybook/react';

import tables from './tables.twig'

/**
 * Add storybook definition for Buttons.
 */
storiesOf('Atoms/Tables', module)
  .add('Twig button', () =>
    <div dangerouslySetInnerHTML={{__html: tables({})}}></div>
  )
