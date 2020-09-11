import { join } from 'path';
import { render, Twig } from 'twig-testing-library';

import loadYaml from '../../../util/loadYaml';
import { setupTwig, namespaces } from '../../../.storybook/setupTwig';

setupTwig(Twig);

describe('card', () => {
  it('can render a card', async () => {
    const { container } = await render(
      join(__dirname, 'card.twig'),
      loadYaml(join(__dirname, 'card.yml')),
      namespaces,
    );

    expect(container).toMatchSnapshot();
  });

  it('can render a card with a background', async () => {
    const { container } = await render(
      join(__dirname, 'card.twig'),
      loadYaml(join(__dirname, 'card-bg.yml')),
      namespaces,
    );

    expect(container).toMatchSnapshot();
  });
});
