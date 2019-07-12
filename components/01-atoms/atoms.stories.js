import React from 'react';
import { storiesOf } from '@storybook/react';
import { action } from '@storybook/addon-actions';

import Button from './button/Button.component';

/**
 * Add storybook definition for Button.
 */
storiesOf('Atoms/Button', module)
  .add('Default', () => (
    <Button onClick={action('button-clicked')}>Click Here</Button>
  ))
