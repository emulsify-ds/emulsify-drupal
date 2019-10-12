import React from 'react';
import { storiesOf } from '@storybook/react';

import link from './link.twig';

const linkComponent = (
  link({
    link_attributes: {
      target: '_blank',
    },
    link_url: 'https://github.com/fourkitchens/gatsby-starter-emulsify-drupal',
    link_content: 'This is my link text',
  })
);

/**
 * Add storybook definition for Links.
 */
storiesOf('Atoms/Links', module)
  .add('Links', () => (
    <div dangerouslySetInnerHTML={{ __html: linkComponent }} />
  ));
