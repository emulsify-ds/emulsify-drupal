import button from './twig/button.twig';

import buttonData from './twig/button.yml';
import buttonAltData from './twig/button-alt.yml';

/**
 * Storybook Definition.
 */
export default { title: 'Atoms/Button' };

export const twig = () => button(buttonData);

export const twigAlt = () => button(buttonAltData);
