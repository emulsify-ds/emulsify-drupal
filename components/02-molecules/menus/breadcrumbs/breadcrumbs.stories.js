import React from 'react';
import { storiesOf } from '@storybook/react';

import breadcrumbs from './breadcrumbs.twig';

import breadcrumbsData from './breadcrumbs.yml';

/**
 * Add storybook definition for Breadcrumbs.
 */
storiesOf('Molecules/Menus', module)
  .add('breadcrumbs', () => <div dangerouslySetInnerHTML={{ __html: breadcrumbs(breadcrumbsData) }} />);
