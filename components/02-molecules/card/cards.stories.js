import React from 'react';
import { storiesOf } from '@storybook/react';

import card from './card.twig';

import cardData from './card.yml';
import cardBgData from './card-bg.yml';

/**
 * Add storybook definition for Cards.
 */
storiesOf('Molecules/Cards', module)
  .add('card', () => <div dangerouslySetInnerHTML={{ __html: card(cardData) }} />)
  .add('card with background', () => <div dangerouslySetInnerHTML={{ __html: card({ ...cardData, ...cardBgData }) }} />);
