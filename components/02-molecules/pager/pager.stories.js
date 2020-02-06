import React from 'react';

import pager from './pager.twig';

import pagerData from './pager.yml';
import pagerEllipsesData from './pager-ellipses.yml';
import pagerPrevEllipsesData from './pager-prev-ellipses.yml';
import pagerBothEllipsesData from './pager-both-ellipses.yml';

/**
 * Storybook Definition.
 */
export default { title: 'Molecules/Menus/Pager' };

export const pagerExample = () => (
  <>
    <h3>Pager:</h3>
    <div dangerouslySetInnerHTML={{ __html: pager(pagerData) }} />
    <h3>Pager with next ellipses:</h3>
    <div
      dangerouslySetInnerHTML={{
        __html: pager({ ...pagerData, ...pagerEllipsesData }),
      }}
    />
    <h3>Pager with both ellipses:</h3>
    <div
      dangerouslySetInnerHTML={{
        __html: pager({ ...pagerData, ...pagerBothEllipsesData }),
      }}
    />
    <h3>Pager with previous ellipses:</h3>
    <div
      dangerouslySetInnerHTML={{
        __html: pager({ ...pagerData, ...pagerPrevEllipsesData }),
      }}
    />
  </>
);
