import React from 'react';
import { storiesOf } from '@storybook/react';

import { social_menu__items, footer_menu__items } from '../../../.storybook/globalData';

import home from './home.twig';

/**
 * Add storybook definition for Landing Pages.
 */
storiesOf('Pages/Landing Pages', module)
  .add('Home', () =>
    <div dangerouslySetInnerHTML={{__html: home({ social_menu__items, footer_menu__items })}}></div>
  )
