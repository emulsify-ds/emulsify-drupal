import { join } from 'path';
import { render, Twig } from 'twig-testing-library';

import loadYaml from '../../../../util/loadYaml';
import { setupTwig, namespaces } from '../../../../.storybook/setupTwig';

setupTwig(Twig);

describe('social', () => {
  it('can render a social menu', async () => {
    const { container } = await render(
      join(__dirname, 'social-menu.twig'),
      loadYaml(join(__dirname, 'social-menu.yml')),
      namespaces,
    );

    expect(container).toMatchSnapshot();
  });
});
