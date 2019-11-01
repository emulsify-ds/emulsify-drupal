import React from 'react';

import paragraph from './03-inline-elements.twig';
import blockquote from './02-blockquote.twig';
import pre from './05-pre.twig';

import blockquoteData from './blockquote.yml';

/**
 * Storybook Definition.
 */
export default { title: 'Atoms/Text' };

export const various = () => <div dangerouslySetInnerHTML={{ __html: paragraph({}) }} />;
export const blockquoteExample = () => (
  <div dangerouslySetInnerHTML={{ __html: blockquote(blockquoteData) }} />);
export const preformatted = () => <div dangerouslySetInnerHTML={{ __html: pre({}) }} />;
