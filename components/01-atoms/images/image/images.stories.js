import React from 'react';
import { storiesOf } from '@storybook/react';

import './image.css';
import image from './responsive-image.twig';
import figure from './figure.twig';

const imageComponent = (
  image({
    output_image_tag: true,
    image_srcset: 'https://placeimg.com/320/180/any 320w, https://placeimg.com/480/270/any 480w, https://placeimg.com/640/360/any 640w, https://placeimg.com/800/450/any 800w, https://placeimg.com/960/540/any 960w, https://placeimg.com/1120/630/any 1120w, https://placeimg.com/1280/720/any 1280w, https://placeimg.com/1440/810/any 1440w, https://placeimg.com/1600/900/any 1600w, https://placeimg.com/1760/990/any 1760w, https://placeimg.com/1920/1080/any 1920w, https://placeimg.com/2080/1170/any 2080w, https://placeimg.com/2240/1260/any 2240w',
    image_sizes: '100vw',
    image_src: 'https://placeimg.com/320/180/any',
    image_alt: 'A 16 by 9 image',
  })
);

const figureComponent = (
  figure({
    output_image_tag: true,
    image_url: '#',
    image_src: 'https://placeimg.com/1200/200/tech',
    image_alt: 'This is the alt text',
    image_caption: 'This is an image caption.',
  })
);

/**
 * Add storybook definition for images.
 */
storiesOf('Atoms/Images', module)
  .add('Images', () => (
    <div dangerouslySetInnerHTML={{ __html: imageComponent }} />
  ))
  .add('Figure', () => (
    <div dangerouslySetInnerHTML={{ __html: figureComponent }} />
  ));
