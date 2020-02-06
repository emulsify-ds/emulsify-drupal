import React from 'react';

import placeHolderTwig from './place-holder.twig';

/**
 * Storybook Definition.
 */
export default { title: 'Templates/Place Holder' };

export const placeHolder = () => (
  <div dangerouslySetInnerHTML={{ __html: placeHolderTwig({}) }} />
);
