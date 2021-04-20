import status from './status.twig';

import statusData from './status.yml';

/**
 * Storybook Definition.
 */
export default { title: 'Molecules/Status' };

export const statusExamples = () => status(statusData);
