import { renderTwig } from '@emulsify/core/storybook';
import template from './emulsify-smoke.twig';
import './emulsify-smoke.scss';

export default {
  title: 'Consumer/Status panel',
  render: renderTwig(template),
  args: {
    title: 'Your component library is ready',
    description: 'This project-owned Twig component is built and rendered by the generated theme.',
    url: 'https://www.emulsify.info',
    link_text: 'Read the Emulsify documentation',
  },
};

export const Default = {};
