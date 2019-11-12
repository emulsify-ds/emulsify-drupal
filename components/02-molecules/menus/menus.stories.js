import React from 'react';
import { useEffect } from '@storybook/client-api';

import breadcrumb from './breadcrumbs/breadcrumbs.twig';
import inlineMenu from './inline/inline-menu.twig';
import mainMenu from './main-menu/main-menu.twig';
import socialMenu from './social/social-menu.twig';
import tabs from './tabs/tabs.twig';

import breadcrumbsData from './breadcrumbs/breadcrumbs.yml';
import inlineMenuData from './inline/inline-menu.yml';
import mainMenuData from './main-menu/main-menu.yml';
import socialMenuData from './social/social-menu.yml';
import tabData from './tabs/tabs.yml';

import mainMenuJS from './main-menu/main-menu';
import tabMenuJS from './tabs/tabs';

/**
 * Storybook Definition.
 */
export default { title: 'Molecules/Menus' };

export const breadcrumbs = () => (
  <div dangerouslySetInnerHTML={{ __html: breadcrumb(breadcrumbsData) }} />);
export const inline = () => (
  <div dangerouslySetInnerHTML={{ __html: inlineMenu(inlineMenuData) }} />);
export const main = () => {
  useEffect(() => {
    mainMenuJS();
  }, []);
  return <div dangerouslySetInnerHTML={{ __html: mainMenu(mainMenuData) }} />;
};
export const social = () => (
  <div dangerouslySetInnerHTML={{ __html: socialMenu(socialMenuData) }} />);
export const primaryTabs = () => {
  useEffect(() => {
    tabMenuJS();
  }, []);
  return <div dangerouslySetInnerHTML={{ __html: tabs(tabData) }} />;
};
