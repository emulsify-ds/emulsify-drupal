import React from 'react';

import animations from './animations.twig';

/**
 * Add storybook definition for Animations.
 */
export default { title: 'Base/Animations' };

export const Fade = () => <div dangerouslySetInnerHTML={{ __html: animations({ animation_name: 'Fade' }) }} />;
