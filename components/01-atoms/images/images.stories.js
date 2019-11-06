import React from 'react';

import image from './image/responsive-image.twig';
import figure from './image/figure.twig';
import iconTwig from './icons/icons.twig';

import imageData from './image/image.yml';
import figureData from './image/figure.yml';
import iconData from './icons/icons.yml';

/**
 * Storybook Definition.
 */
export default { title: 'Atoms/Images' };

export const images = () => <div dangerouslySetInnerHTML={{ __html: image(imageData) }} />;
export const figures = () => <div dangerouslySetInnerHTML={{ __html: figure(figureData) }} />;
export const icons = () => <div dangerouslySetInnerHTML={{ __html: iconTwig(iconData) }} />;
