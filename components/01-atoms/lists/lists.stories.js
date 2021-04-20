import React from 'react';

import dl from './dl.twig';
import ul from './ul.twig';
import ol from './ol.twig';

import dlData from './dl.yml';
import ulData from './ul.yml';
import olData from './ol.yml';

/**
 * Storybook Definition.
 */
export default { title: 'Atoms/Lists' };

export const definitionList = () => (
  <div dangerouslySetInnerHTML={{ __html: dl(dlData) }} />
);
export const unorderedList = () => (
  <div dangerouslySetInnerHTML={{ __html: ul(ulData) }} />
);
export const orderedList = () => (
  <div dangerouslySetInnerHTML={{ __html: ol(olData) }} />
);
