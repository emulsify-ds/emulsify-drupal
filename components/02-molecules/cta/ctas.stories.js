import React from 'react';
import { storiesOf } from '@storybook/react';

import cta from './cta.twig';

import ctaData from './cta.yml';

/**
 * Add storybook definition for CTAs.
 */
storiesOf('Molecules/CTA', module)
  .add('cta', () => <div dangerouslySetInnerHTML={{ __html: cta(ctaData) }} />);
