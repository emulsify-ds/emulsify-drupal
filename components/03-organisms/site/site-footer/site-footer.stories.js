import React from 'react';
import { storiesOf } from '@storybook/react';

import footer from './site-footer.twig';

import footerSocial from '../../../02-molecules/menus/social/social-menu.yml';
import footerMenu from '../../../02-molecules/menus/inline/inline-menu.yml';

/**
 * Add storybook definition for footers.
 */
storiesOf('Organisms/Site', module)
  .add('Footer', () => <div dangerouslySetInnerHTML={{ __html: footer({ ...footerSocial, ...footerMenu }) }} />);
