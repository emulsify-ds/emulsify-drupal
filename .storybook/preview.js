import { addDecorator } from '@storybook/html';
import { useEffect } from '@storybook/client-api';
import Twig from 'twig';
import { setupTwig } from './setupTwig';

// GLOBAL CSS
import '../components/style.scss';

// If in a Drupal project, it's recommended to import a symlinked version of drupal.js.
import './_drupal.js';

export const decorators = [
  (Story, { args }) => {
    const { renderAs } = args || {};

    // Usual emulsify hack to add Drupal behaviors.
    useEffect(() => {
      Drupal.attachBehaviors();
    }, [args]);
    return Story();
  },
];

setupTwig(Twig);

export const parameters = {
  actions: { argTypesRegex: '^on[A-Z].*' },
};
