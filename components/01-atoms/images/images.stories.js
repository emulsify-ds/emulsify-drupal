import image from './image/responsive-image.twig';
import figure from './image/figure.twig';
import iconTwig from './icons/icons.twig';

import imageData from './image/image.yml';
import figureData from './image/figure.yml';

const svgIcons = require.context('../../../images/icons/', true, /\.svg$/);

/**
 * Storybook Definition.
 */
export default { title: 'Atoms/Images' };

export const images = () => image(imageData);

export const figures = () => figure(figureData);

const items = [];
svgIcons.keys().forEach((key) => {
  const iconName = svgIcons(key).split('static/media/').pop().split('.')[0];
  const icon = {};
  icon.value = iconName;
  items.push(icon);
});

export const icons = () => iconTwig({ items });
