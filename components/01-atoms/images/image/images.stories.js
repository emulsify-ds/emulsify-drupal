import React from 'react';

import image from './responsive-image.twig';
import figure from './figure.twig';

import imageData from './image.yml';

import figureData from './figure.yml';

/**
 * Storybook Definition.
 */
export default { title: 'Atoms/Images' };

export const images = () => <div dangerouslySetInnerHTML={{ __html: image(imageData) }} />;
export const figures = () => <div dangerouslySetInnerHTML={{ __html: figure(figureData) }} />;
