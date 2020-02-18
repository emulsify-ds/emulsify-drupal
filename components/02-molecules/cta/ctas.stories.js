import React from 'react';

import cta from './cta.twig';

import ctaData from './cta.yml';

/**
 * Storybook Definition.
 */
export default { title: 'Molecules/CTA' };

export const ctaExample = () => (
  <div dangerouslySetInnerHTML={{ __html: cta(ctaData) }} />
);
