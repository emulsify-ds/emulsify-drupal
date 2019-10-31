import React from 'react';
import { storiesOf } from '@storybook/react';
import { useEffect } from '@storybook/client-api';

import mainMenu from '../../../02-molecules/menus/main-menu/main-menu';

import siteHeader from './site-header.twig';

import breadcrumbData from '../../../02-molecules/menus/breadcrumbs/breadcrumbs.yml';
import mainMenubData from '../../../02-molecules/menus/main-menu/main-menu.yml';

/**
 * Add storybook definition for Links.
 */
storiesOf('Organisms/Site', module)
  .add('Header', () => {
    useEffect(() => {
      mainMenu();
    }, []);
    return <div dangerouslySetInnerHTML={{ __html: siteHeader({ ...breadcrumbData, ...mainMenubData }) }} />;
  });
