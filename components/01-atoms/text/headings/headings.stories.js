import React from 'react';
import { storiesOf } from '@storybook/react';

import headings from './headings.twig';

/**
 * Add storybook definition for Headings.
 */
storiesOf('Atoms/Headings', module)
  .add('Headings', () => (
    <div dangerouslySetInnerHTML={{ __html: headings({}) }} />
  ));
