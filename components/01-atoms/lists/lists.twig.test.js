import { join } from 'path';
import { render, Twig } from 'twig-testing-library';

import loadYaml from '../../../util/loadYaml';
import { setupTwig, namespaces } from '../../../.storybook/setupTwig';

setupTwig(Twig);

describe('lists', () => {
  it('can render an dl list', async () => {
    const { container } = await render(
      join(__dirname, 'dl.twig'),
      loadYaml(join(__dirname, 'dl.yml')),
      namespaces,
    );

    expect(container).toMatchSnapshot();
  });

  it('can render an ol list', async () => {
    const { container } = await render(
      join(__dirname, 'ol.twig'),
      loadYaml(join(__dirname, 'ol.yml')),
      namespaces,
    );

    expect(container).toMatchSnapshot();
  });

  it('can render an ul list', async () => {
    const { container } = await render(
      join(__dirname, 'ul.twig'),
      loadYaml(join(__dirname, 'ul.yml')),
      namespaces,
    );

    expect(container).toMatchSnapshot();
  });
});
