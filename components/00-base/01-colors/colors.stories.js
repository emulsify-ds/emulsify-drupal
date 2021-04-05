import React from 'react';

import colors from './colors.twig';

import colorsData from './colors.yml';

import './colors';

/**
 * Storybook Definition.
 */
export default { title: 'Base/Colors' };

export const Palettes = () => {
  return <div dangerouslySetInnerHTML={{ __html: colors(colorsData) }} />;
};
