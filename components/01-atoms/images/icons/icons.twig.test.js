import { join } from 'path';
import { render, Twig } from 'twig-testing-library';

import loadYaml from '../../../../util/loadYaml';
import { setupTwig, namespaces } from '../../../../.storybook/setupTwig';

setupTwig(Twig);

describe('icons', () => {
  it('can render an icon', async () => {
    const { container } = await render(
      join(__dirname, 'icons.twig'),
      loadYaml(join(__dirname, 'icons.yml')),
      namespaces,
    );

    expect(container).toMatchSnapshot();
  });
});
