import React from 'react';
import { storiesOf } from '@storybook/react';

import { social_menu__items, footer_menu__items } from '../../../.storybook/globalData';

import article from './article.twig';

/**
 * Add storybook definition for Content Types.
 */
storiesOf('Pages/Content Types', module)
  .add('Article', () =>
    <div dangerouslySetInnerHTML={{__html: article({ social_menu__items, footer_menu__items })}}></div>
  )
