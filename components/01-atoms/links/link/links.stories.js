import React from 'react';
import { storiesOf } from '@storybook/react';

import link from './link.twig';

import linkData from './link.yml';

/**
 * Add storybook definition for Links.
 */
storiesOf('Atoms/Links', module)
  .add('Links', () => (
    <div dangerouslySetInnerHTML={{ __html: link(linkData) }} />
  ));
