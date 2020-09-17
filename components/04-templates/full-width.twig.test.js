import { join } from 'path';
import { render, Twig } from 'twig-testing-library';

import { setupTwig, namespaces } from '../../.storybook/setupTwig';

setupTwig(Twig);

describe('full-width', () => {
  it('can render a full width template', async () => {
    const { container } = await render(
      join(__dirname, 'full-width.twig'),
      {},
      namespaces,
    );

    expect(container).toMatchSnapshot();
  });
});
