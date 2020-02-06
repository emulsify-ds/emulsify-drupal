import React from 'react';

import card from './card.twig';

import cardData from './card.yml';
import cardBgData from './card-bg.yml';

/**
 * Storybook Definition.
 */
export default { title: 'Molecules/Cards' };

export const cardExample = () => (
  <div dangerouslySetInnerHTML={{ __html: card(cardData) }} />
);
export const cardWithBackground = () => (
  <div
    dangerouslySetInnerHTML={{ __html: card({ ...cardData, ...cardBgData }) }}
  />
);
