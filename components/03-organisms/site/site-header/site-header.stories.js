import React from 'react';
import { storiesOf } from '@storybook/react';

import siteHeader from './site-header.twig'
const siteHeaderTwig = (
  siteHeader({
    logo_link__url: "#"
  })
)

/**
 * Add storybook definition for Links.
 */
storiesOf('Organisms/Site', module)
  .add('Header', () => (
    <div dangerouslySetInnerHTML={{__html: siteHeaderTwig}}></div>
  ))
