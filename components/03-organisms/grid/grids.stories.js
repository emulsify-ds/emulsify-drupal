import grid from './grid.twig';

import gridData from './grid.yml';
import gridCardData from './grid-cards.yml';
import gridCtaData from './grid-ctas.yml';

/**
 * Storybook Definition.
 */
export default { title: 'Organisms/Grids' };

export const defaultGrid = () => grid(gridData);

export const cardGrid = () => grid({ ...gridData, ...gridCardData });

export const ctaGrid = () => grid({ ...gridData, ...gridCtaData });
