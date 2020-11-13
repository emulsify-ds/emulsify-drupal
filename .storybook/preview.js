import React from 'react';
import { useEffect } from '@storybook/client-api';
import Twig from 'twig';
import { setupTwig } from './setupTwig';

// GLOBAL CSS
import '../components/style.scss';

// GLOBAL Data
import globalData from '../components/00-base/09-data/global.yml';
export const globalTypes = {
  data: globalData,
};

// If in a Drupal project, it's recommended to import a symlinked version of drupal.js.
import './_drupal.js';

export const decorators = [
  (Story, context) => {
    useEffect(() => Drupal.attachBehaviors(), []);
    return <Story data={context.globals.data} />;
  },
];

setupTwig(Twig);
