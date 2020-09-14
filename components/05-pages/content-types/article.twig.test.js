import { join } from 'path';
import { render, Twig } from 'twig-testing-library';

import { setupTwig, namespaces } from '../../../.storybook/setupTwig';

setupTwig(Twig);

describe('article', () => {
  it('can render an article page', async () => {
    const { container } = await render(
      join(__dirname, 'article.twig'),
      {},
      namespaces,
    );

    expect(container).toMatchSnapshot();
  });
});
