import React from 'react';
import { storiesOf } from '@storybook/react';

import pager from './pager.twig'
const pagerTwig = (
  pager({
    pager__uid: 1,
    current: 1,
    items: {
      previous: true,
      next: true,
      pages: {
        1: {
          href: "#"
        },
        2: {
          href: "#"
        },
        3: {
          href: "#"
        },
        4: {
          href: "#"
        },
      },
    },
  })
)

const pagerPrevTwig = (
  pager({
    pager__uid: 2,
    current: 5,
    ellipses: {
      previous: true,
    },
    items: {
      previous: true,
      next: true,
      pages: {
        5: {
          href: "#"
        },
        6: {
          href: "#"
        },
        7: {
          href: "#"
        },
        8: {
          href: "#"
        },
      },
    },
  })
)

const pagerNextTwig = (
  pager({
    pager__uid: 3,
    current: 5,
    ellipses: {
      next: true,
    },
    items: {
      previous: true,
      next: true,
      pages: {
        1: {
          href: "#"
        },
        2: {
          href: "#"
        },
        3: {
          href: "#"
        },
        4: {
          href: "#"
        },
      },
    },
  })
)

const pagerBothTwig = (
  pager({
    pager__uid: 4,
    current: 8,
    ellipses: {
      previous: true,
      next: true
    },
    items: {
      previous: true,
      next: true,
      pages: {
        3: {
          href: "#"
        },
        4: {
          href: "#"
        },
        5: {
          href: "#"
        },
        6: {
          href: "#"
        },
      },
    },
  })
)

/**
 * Add storybook definition for CTAs.
 */
storiesOf('Molecules/Pager', module)
  .add('pager', () =>
    <>
      <h3>Pager:</h3>
      <div dangerouslySetInnerHTML={{__html: pagerTwig}}></div>
      <h3>Pager with next ellipses:</h3>
      <div dangerouslySetInnerHTML={{__html: pagerNextTwig}}></div>
      <h3>Pager with both ellipses:</h3>
      <div dangerouslySetInnerHTML={{__html: pagerBothTwig}}></div>
      <h3>Pager with previous ellipses:</h3>
      <div dangerouslySetInnerHTML={{__html: pagerPrevTwig}}></div>
    </>
  )
