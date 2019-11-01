import React from 'react';

import icons from './icons.twig';

import iconData from './icons.yml';

/**
 * Storybook Definition.
 */
export default { title: 'Atoms/Icons' };

export const defaultIcons = () => <div dangerouslySetInnerHTML={{ __html: icons(iconData) }} />;
