import { join } from 'path';
import { render, Twig } from 'twig-testing-library';

import loadYaml from '../../../../util/loadYaml';
import { setupTwig, namespaces } from '../../../../.storybook/setupTwig';

setupTwig(Twig);

describe('heading', () => {
  it('can render a heading', async () => {
    const { container } = await render(
      join(__dirname, '_heading.twig'),
      loadYaml(join(__dirname, 'headings.yml')),
      namespaces,
    );

    expect(container).toMatchInlineSnapshot(`
      <div>
        


        <h
          class="h"
        >
          
            
        
        </h>
        

      </div>
    `);
  });
});
