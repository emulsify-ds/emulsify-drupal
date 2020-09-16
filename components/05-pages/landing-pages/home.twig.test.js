import { join } from 'path';
import { render, Twig } from 'twig-testing-library';

import { setupTwig, namespaces } from '../../../.storybook/setupTwig';

setupTwig(Twig);

describe('home', () => {
  it('can render a home page', async () => {
    const { container } = await render(
      join(__dirname, 'home.twig'),
      {},
      namespaces,
    );

    expect(container).toMatchSnapshot();
  });
});
