import React from 'react';
import { storiesOf } from '@storybook/react';

import fullWidth from './full-width.twig';
import withSidebar from './with-sidebar.twig';

/**
 * Add storybook definition for CTAs.
 */
storiesOf('Templates/Layouts', module)
  .add('Full Width', () =>
    <div dangerouslySetInnerHTML={{__html: fullWidth({})}}></div>
  )
  .add('With Sidebar', () =>
    <div dangerouslySetInnerHTML={{__html: withSidebar({})}}></div>
  )
