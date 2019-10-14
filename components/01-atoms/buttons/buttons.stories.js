import React from 'react';
import { storiesOf } from '@storybook/react';
import { useEffect } from '@storybook/client-api';

import Button from './react/Button.component';

import button from './twig/button.twig';

import { buttonLog } from './twig/button';

const buttonTwig = (
  button({ button_content: 'Twig Button' })
);

const buttonAlt = (
  button({
    button_content: 'Button Alternative',
    button_modifiers: ['alt'],
  })
);

const buttonAlt2 = (
  button({
    button_content: 'Button Alternative',
    button_modifiers: ['alt-2'],
  })
);

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
    return <div dangerouslySetInnerHTML={{ __html: buttonTwig }} />;
  })
  .add('Button Alternative', () => <div dangerouslySetInnerHTML={{ __html: buttonAlt }} />)
  .add('Button Alternative 2', () => <div dangerouslySetInnerHTML={{ __html: buttonAlt2 }} />);
