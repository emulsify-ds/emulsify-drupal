import React from 'react';
import { storiesOf } from '@storybook/react';

import icons from './icons.twig';

import iconData from './icons.yml';

/**
 * Add storybook definition for Icon.
 */
storiesOf('Atoms/Icons', module)
  .add('Icons', () => (
    <div dangerouslySetInnerHTML={{ __html: icons(iconData) }} />
  ));
