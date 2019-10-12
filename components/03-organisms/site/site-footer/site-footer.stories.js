import React from 'react';
import { storiesOf } from '@storybook/react';

import footer from './site-footer.twig'
const siteFooterTwig = (
  footer({})
)

/**
 * Add storybook definition for footers.
 */
storiesOf('Organisms/Site', module)
  .add('Footer', () =>
    <div dangerouslySetInnerHTML={{__html: siteFooterTwig}}></div>
  )
