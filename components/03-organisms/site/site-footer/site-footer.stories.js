import React from 'react';
import { storiesOf } from '@storybook/react';

import footer from './site-footer.twig';

const siteFooterTwig = (
  footer({
    social_menu__items: {
      1: {
        title: 'Twitter',
        url: '#',
        icon: 'twitter',
      },
      2: {
        title: 'Facebook',
        url: '#',
        icon: 'facebook',
      },
      3: {
        title: 'Instagram',
        url: '#',
        icon: 'instagram',
      },
    },
    footer_menu__items: {
      1: {
        title: 'Item 1',
        url: '#',
      },
      2: {
        title: 'Item 2',
        url: '#',
      },
      3: {
        title: 'Item 3',
        url: '#',
      },
    },
  })
);

/**
 * Add storybook definition for footers.
 */
storiesOf('Organisms/Site', module)
  .add('Footer', () => <div dangerouslySetInnerHTML={{ __html: siteFooterTwig }} />);
