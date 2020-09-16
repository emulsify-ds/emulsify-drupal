import { join } from 'path';
import { render, Twig } from 'twig-testing-library';

import loadYaml from '../../../util/loadYaml';
import { setupTwig, namespaces } from '../../../.storybook/setupTwig';

setupTwig(Twig);

describe('grid', () => {
  it('can render a grid', async () => {
    const { container } = await render(
      join(__dirname, 'grid.twig'),
      loadYaml(join(__dirname, 'grid.yml')),
      namespaces,
    );

    expect(container).toMatchSnapshot();
  });

  it('can render a grid of cards', async () => {
    const { container } = await render(
      join(__dirname, 'grid.twig'),
      loadYaml(join(__dirname, 'grid-cards.yml')),
      namespaces,
    );

    expect(container).toMatchSnapshot();
  });

  it('can render a grid of ctas', async () => {
    const { container } = await render(
      join(__dirname, 'grid.twig'),
      loadYaml(join(__dirname, 'grid-ctas.yml')),
      namespaces,
    );

    expect(container).toMatchSnapshot();
  });
});
