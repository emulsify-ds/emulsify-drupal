import React from 'react';
import { storiesOf } from '@storybook/react';

import article from './article.twig';

/**
 * Add storybook definition for Content Types.
 */
storiesOf('Pages/Content Types', module)
  .add('Article', () =>
    <div dangerouslySetInnerHTML={{__html: article({})}}></div>
  )
