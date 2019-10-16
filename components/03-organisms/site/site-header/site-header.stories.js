import React from 'react';
import { storiesOf } from '@storybook/react';
import { useEffect } from '@storybook/client-api';

import { globalData } from '../../../../.storybook/globalData';

import mainMenu from '../../../02-molecules/menus/main-menu/main-menu';

import siteHeader from './site-header.twig';

const siteHeaderTwig = (
  siteHeader({
    breadcrumb: globalData.breadcrumb,
    menu_items: globalData.main_menu,
    logo_link__url: '#',
  })
);

/**
 * Add storybook definition for Links.
 */
storiesOf('Organisms/Site', module)
  .add('Header', () => {
    useEffect(() => {
      mainMenu();
    }, []);
    return <div dangerouslySetInnerHTML={{ __html: siteHeaderTwig }} />;
  });
