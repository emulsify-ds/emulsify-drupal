import React from 'react';
import { storiesOf } from '@storybook/react';

import status from './status.twig';

import statusData from './status.yml';

/**
 * Add storybook definition for Statuses.
 */
storiesOf('Molecules/Status', module)
  .add('status', () => <div dangerouslySetInnerHTML={{ __html: status(statusData) }} />);
