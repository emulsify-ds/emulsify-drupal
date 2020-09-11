import { join } from 'path';
import { render, Twig } from 'twig-testing-library';

import { setupTwig } from '../../../.storybook/setupTwig';

setupTwig(Twig);

describe('tables', () => {
  it('can render a table', async () => {
    const { container } = await render(join(__dirname, 'tables.twig'));

    expect(container).toMatchSnapshot();
  });
});
