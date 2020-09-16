import { join } from 'path';
import { render, Twig } from 'twig-testing-library';

import { setupTwig } from '../../../../.storybook/setupTwig';

setupTwig(Twig);

describe('textfields', () => {
  it('can render textfield form items', async () => {
    const { container } = await render(join(__dirname, 'textfields.twig'));

    expect(container).toMatchSnapshot();
  });
});
