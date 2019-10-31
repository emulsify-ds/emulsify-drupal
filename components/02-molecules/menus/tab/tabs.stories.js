import React from 'react';
import { storiesOf } from '@storybook/react';
import { useEffect } from '@storybook/client-api';

import tabs from './tabs.twig';

import tabMenu from './tabs';

import tabData from './tabs.yml';

/**
 * Add storybook definition for Lists.
 */
storiesOf('Molecules/Menus', module)
  .add('Primary Tabs', () => {
    useEffect(() => {
      tabMenu();
    }, []);
    return <div dangerouslySetInnerHTML={{ __html: tabs(tabData) }} />;
  });
