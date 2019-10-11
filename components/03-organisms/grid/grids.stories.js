import React from 'react';
import { storiesOf } from '@storybook/react';

import grid from './grid.twig'

const Grid = (
  grid({
    grid_label: "Default",
    items: {
      1: "",
      2: "",
      3: ""
    }
  })
)

const cardGrid = (
  grid({
    grid_label: "Card Grid",
    item_type: "card",
    items: {
      1: {
        card__image__src: "https://placeimg.com/480/300/people",
        card__image__output_image_tag: true,
        card__heading: "This is a title too",
        card__link__url: "#",
        card__subheading: "Person of Interest",
        card__body: "Curabitur non nulla sit amet nisl tempus convallis quis ac lectus. Vivamus magna justo, lacinia eget consectetur sed, convallis at tellus. Donec rutrum congue leo eget malesuada. Proin eget tortor risus. Sed porttitor lectus nibh. Nulla quis lorem ut libero malesuada feugiat. Vivamus magna justo, lacinia eget consectetur sed, convallis at tellus. Pellentesque in ipsum id orci porta dapibus. Nulla quis lorem ut libero malesuada feugiat. Nulla quis lorem ut libero malesuada feugiat."
      },
      2: {
        card__image__src: "https://placeimg.com/480/300/people",
        card__image__output_image_tag: true,
        card__heading: "This is a title too",
        card__subheading: "Person of Interest",
        card__body: "Curabitur non nulla sit amet nisl tempus convallis quis ac lectus. Vivamus magna justo, lacinia eget consectetur sed, convallis at tellus. Donec rutrum congue leo eget malesuada. Proin eget tortor risus. Sed porttitor lectus nibh. Nulla quis lorem ut libero malesuada feugiat. Vivamus magna justo, lacinia eget consectetur sed, convallis at tellus. Pellentesque in ipsum id orci porta dapibus. Nulla quis lorem ut libero malesuada feugiat. Nulla quis lorem ut libero malesuada feugiat."
      },
      3: {
        card__image__src: "https://placeimg.com/480/300/people",
        card__image__output_image_tag: true,
        card__heading: "This is a title too",
        card__subheading: "Person of Interest",
        card__body: "Curabitur non nulla sit amet nisl tempus convallis quis ac lectus. Vivamus magna justo, lacinia eget consectetur sed, convallis at tellus. Donec rutrum congue leo eget malesuada. Proin eget tortor risus. Sed porttitor lectus nibh. Nulla quis lorem ut libero malesuada feugiat. Vivamus magna justo, lacinia eget consectetur sed, convallis at tellus. Pellentesque in ipsum id orci porta dapibus. Nulla quis lorem ut libero malesuada feugiat. Nulla quis lorem ut libero malesuada feugiat."
      }
    }
  })
)

const ctaGrid = (
  grid({
    grid_label: "CTA Grid",
    item_type: "cta",
    items: {
      1: {
        cta__heading: "This is an awesome CTA",
        cta__button_text: "Click me!",
      },
      2: {
        cta__heading: "This is an even better CTA!!",
        cta__button_text: "No, click me!",
      },
      3: {
        cta__heading: "This is a boring CTA",
        cta__button_text: "Click me, I guess...",
      }
    }
  })
)

/**
 * Add storybook definition for Grids.
 */
storiesOf('Organisms/Grids', module)
  .add('Default', () => (
    <div dangerouslySetInnerHTML={{__html: Grid }}></div>
  ))
  .add('Card Grid', () => (
    <div dangerouslySetInnerHTML={{__html: cardGrid }}></div>
  ))
  .add('CTA Grid', () => (
    <div dangerouslySetInnerHTML={{__html: ctaGrid }}></div>
  ))
