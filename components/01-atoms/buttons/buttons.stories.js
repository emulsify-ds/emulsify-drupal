import React from 'react';
import { storiesOf } from '@storybook/react';
import { useEffect } from '@storybook/client-api';

import Button from './react/Button.component';

import button from './twig/button.twig';

import buttonLog from './twig/button';

import buttonData from './twig/button.yml';
import buttonAltData from './twig/button-alt.yml';
import buttonAlt2Data from './twig/button-alt2.yml';

/**
 * Add storybook definition for Buttons.
 */
storiesOf('Atoms/Buttons', module)
  .add('React button', () => (
    <Button>React Button</Button>
  ))
  .add('Twig button', () => {
    useEffect(() => {
      buttonLog();
    }, []);
    return <div dangerouslySetInnerHTML={{ __html: button(buttonData) }} />;
  })
  .add('Button Alternative', () => <div dangerouslySetInnerHTML={{ __html: button(buttonAltData) }} />)
  .add('Button Alternative 2', () => <div dangerouslySetInnerHTML={{ __html: button(buttonAlt2Data) }} />);
