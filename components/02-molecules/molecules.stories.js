import React from 'react';
import { storiesOf } from '@storybook/react';
import { action } from '@storybook/addon-actions';

import cta from './cta/cta.twig'
const ctaTwig = (
  cta({
    cta__heading: "CTA Heading Text",
    cta__button_text: "CTA Button Text"
  })
)

/**
 * Add storybook definition for Button.
 */
storiesOf('Molecules/CTA', module)
  .add('cta', () => 
    <div dangerouslySetInnerHTML={{__html: ctaTwig}}></div>
  )
