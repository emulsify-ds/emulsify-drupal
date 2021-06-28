import pager from './pager.twig';

import pagerData from './pager.yml';
import pagerEllipsesData from './pager-ellipses.yml';
import pagerPrevEllipsesData from './pager-prev-ellipses.yml';
import pagerBothEllipsesData from './pager-both-ellipses.yml';

/**
 * Storybook Definition.
 */
export default { title: 'Molecules/Menus/Pager' };

export const basic = () => pager(pagerData);

export const withNext = () => pager({ ...pagerData, ...pagerEllipsesData });

export const withBoth = () => pager({ ...pagerData, ...pagerBothEllipsesData });

export const withPrevious = () =>
  pager({ ...pagerData, ...pagerPrevEllipsesData });
