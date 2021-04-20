import video from './video.twig';

import videoData from './video.yml';
import videoFullData from './video-full.yml';

/**
 * Storybook Definition.
 */
export default { title: 'Atoms/Videos' };

export const wide = () => video(videoData);

export const full = () => video(videoFullData);
