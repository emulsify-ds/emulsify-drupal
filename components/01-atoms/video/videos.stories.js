import React from 'react';
import { storiesOf } from '@storybook/react';

import video from './video.twig';

import videoData from './video.yml';
import videoFullData from './video-full.yml';

/**
 * Add storybook definition for Videos.
 */
storiesOf('Atoms/Video', module)
  .add('Wide', () => <div dangerouslySetInnerHTML={{ __html: video(videoData) }} />)
  .add('Full', () => <div dangerouslySetInnerHTML={{ __html: video(videoFullData) }} />);
