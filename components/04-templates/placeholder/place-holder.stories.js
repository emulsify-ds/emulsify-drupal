import React from 'react';
import { storiesOf } from '@storybook/react';

import placeHolder from './_place-holder.twig'
const placeHolderTwig = (
  placeHolder({})
)

/**
 * Add storybook definition for Place Holders.
 */
storiesOf('Templates/Place Holder', module)
  .add('placeHolder', () =>
    <div dangerouslySetInnerHTML={{__html: placeHolderTwig}}></div>
  )
