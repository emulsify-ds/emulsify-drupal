import { join } from 'path';
import { render, Twig } from 'twig-testing-library';

import loadYaml from '../../../../util/loadYaml';
import { setupTwig, namespaces } from '../../../../.storybook/setupTwig';

setupTwig(Twig);

describe('main-menu', () => {
  it('can render an main menu', async () => {
    const { container } = await render(
      join(__dirname, 'main-menu.twig'),
      loadYaml(join(__dirname, 'main-menu.yml')),
      namespaces,
    );

    expect(container).toMatchSnapshot();
  });
});
