import React from 'react';

import headings from './headings.twig';

/**
 * Storybook Definition.
 */
export default { title: 'Atoms/Headings' };

export const headingsExamples = () => <div dangerouslySetInnerHTML={{ __html: headings({}) }} />;
