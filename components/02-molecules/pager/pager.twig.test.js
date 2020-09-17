import { join } from 'path';
import { render, Twig } from 'twig-testing-library';

import loadYaml from '../../../util/loadYaml';
import { setupTwig } from '../../../.storybook/setupTwig';

setupTwig(Twig);

describe('pager', () => {
  it('can render a pager', async () => {
    const { container } = await render(
      join(__dirname, 'pager.twig'),
      loadYaml(join(__dirname, 'pager.yml')),
    );

    expect(container).toMatchSnapshot();
  });

  it('can render a pager with ellipses', async () => {
    const { container } = await render(
      join(__dirname, 'pager.twig'),
      loadYaml(join(__dirname, 'pager-ellipses.yml')),
    );

    expect(container).toMatchSnapshot();
  });

  it('can render a pager with prev ellipses', async () => {
    const { container } = await render(
      join(__dirname, 'pager.twig'),
      loadYaml(join(__dirname, 'pager-prev-ellipses.yml')),
    );

    expect(container).toMatchSnapshot();
  });

  it('can render a pager with prev and next ellipses', async () => {
    const { container } = await render(
      join(__dirname, 'pager.twig'),
      loadYaml(join(__dirname, 'pager-both-ellipses.yml')),
    );

    expect(container).toMatchSnapshot();
  });
});
