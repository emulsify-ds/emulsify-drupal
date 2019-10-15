import React from 'react';
import { storiesOf } from '@storybook/react';

import video from './video.twig';

const videoWide = (
  video({
    video_content: "<iframe title='Emulsify Video' width='854' height='480' frameborder='0' allowfullscreen='allowfullscreen' src='https://www.youtube.com/embed/YRnVnlhjOBs?autoplay=0&amp;start=0'></iframe>"
  })
);

const videoFull = (
  video({
    video_content: "<iframe title='Emulsify Video' width='854' height='480' frameborder='0' allowfullscreen='allowfullscreen' src='https://www.youtube.com/embed/YRnVnlhjOBs?autoplay=0&amp;start=0'></iframe>",
    video_modifiers: ["full"]
  })
);

/**
 * Add storybook definition for Videos.
 */
storiesOf('Atoms/Video', module)
  .add('Wide', () => <div dangerouslySetInnerHTML={{ __html: videoWide }} />)
  .add('Full', () => <div dangerouslySetInnerHTML={{ __html: videoFull }} />);
