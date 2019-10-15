import React from 'react';
import { storiesOf } from '@storybook/react';

import tables from './tables.twig';

/**
 * Add storybook definition for Tables.
 */
storiesOf('Atoms/Tables', module)
  .add('Table', () => <div dangerouslySetInnerHTML={{ __html: tables({}) }} />);
