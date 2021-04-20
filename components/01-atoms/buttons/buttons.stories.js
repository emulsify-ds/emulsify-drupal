// Buttons Stories
import button from './button.twig';

import buttonData from './button.yml';
import buttonAltData from './button-alt.yml';

/**
 * Storybook Definition.
 */
export default { title: 'Atoms/Button' };

export const twig = () => button(buttonData);

export const twigAlt = () => button(buttonAltData);
