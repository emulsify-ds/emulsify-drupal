import React from 'react';

import link from './link.twig';

import linkData from './link.yml';

/**
 * Storybook Definition.
 */
export default { title: 'Atoms/Links' };

export const links = () => (
  <div dangerouslySetInnerHTML={{ __html: link(linkData) }} />
);
