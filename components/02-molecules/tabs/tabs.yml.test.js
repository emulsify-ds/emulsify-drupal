import { join } from 'path';
import { render, Twig } from 'twig-testing-library';

import loadYaml from '../../../util/loadYaml';
import { setupTwig, namespaces } from '../../../.storybook/setupTwig';

setupTwig(Twig);

// @TODO: the javascript file associated with this component
// needs to be tested.
describe('tabs', () => {
  it('can render tabs', async () => {
    const { container } = await render(
      join(__dirname, 'tabs.twig'),
      loadYaml(join(__dirname, 'tabs.yml')),
      namespaces,
    );

    expect(container).toMatchSnapshot();
  });
});
