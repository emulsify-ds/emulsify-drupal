import React from 'react';

import Button from './react/Button.component';

import button from './twig/button.twig';

import buttonData from './twig/button.yml';
import buttonAltData from './twig/button-alt.yml';

/**
 * Storybook Definition.
 */
export default {
  component: Button,
  title: 'Atoms/Button',
};

export const react = () => <Button>React Button</Button>;

export const twig = () => (
  <div dangerouslySetInnerHTML={{ __html: button(buttonData) }} />
);
export const twigAlt = () => (
  <div dangerouslySetInnerHTML={{ __html: button(buttonAltData) }} />
);
