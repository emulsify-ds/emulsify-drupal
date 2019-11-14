import React from 'react';
import { useEffect } from '@storybook/client-api';

import tabs from './tabs.twig';

import tabData from './tabs.yml';

import './tabs';

/**
 * Storybook Definition.
 */
export default { title: 'Molecules/Tabs' };

export const JSTabs = () => {
  useEffect(() => Drupal.attachBehaviors(), []);
  return <div dangerouslySetInnerHTML={{ __html: tabs(tabData) }} />;
};
