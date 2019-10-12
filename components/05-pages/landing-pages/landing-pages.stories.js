import React from 'react';
import { storiesOf } from '@storybook/react';

import home from './home.twig';

/**
 * Add storybook definition for Landing Pages.
 */
storiesOf('Pages/Landing Pages', module)
  .add('Home', () =>
    <div dangerouslySetInnerHTML={{__html: home({})}}></div>
  )
