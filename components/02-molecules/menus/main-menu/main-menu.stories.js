import React from 'react';
import { storiesOf } from '@storybook/react';
import { useEffect } from '@storybook/client-api';

import menu from './main-menu.twig';

import mainMenu from './main-menu';

import menuData from '../../../00-base/global/data/menu.yml';

/**
 * Add storybook definition for Main Menus.
 */
storiesOf('Molecules/Menus', module)
  .add('Main', () => {
    useEffect(() => {
      mainMenu();
    }, []);
    return <div dangerouslySetInnerHTML={{ __html: menu(menuData) }} />;
  });
