import React from 'react';

import colors from './colors.twig';

import grayscale from './colors-grayscale.yml';
import branding from './colors-branding.yml';

/**
 * Storybook Definition.
 */
export default { title: 'Base/Colors' };

export const Grayscale = () => <div dangerouslySetInnerHTML={{ __html: colors(grayscale) }} />;
export const Branding = () => <div dangerouslySetInnerHTML={{ __html: colors(branding) }} />;
