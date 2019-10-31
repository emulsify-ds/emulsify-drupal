import React from 'react';
import { storiesOf } from '@storybook/react';

import dl from './dl.twig';
import ul from './ul.twig';
import ol from './ol.twig';

import dlData from './dl.yml';
import ulData from './ul.yml';
import olData from './ol.yml';

/**
 * Add storybook definition for Lists.
 */
storiesOf('Atoms/Lists', module)
  .add('Definition List', () => (
    <div dangerouslySetInnerHTML={{ __html: dl(dlData) }} />
  ))
  .add('Unordered List', () => (
    <div dangerouslySetInnerHTML={{ __html: ul(ulData) }} />
  ))
  .add('Ordered List', () => (
    <div dangerouslySetInnerHTML={{ __html: ol(olData) }} />
  ));
