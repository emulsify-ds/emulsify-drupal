import { join } from 'path';
import { render, Twig } from 'twig-testing-library';

import { setupTwig, namespaces } from '../../../.storybook/setupTwig';

setupTwig(Twig);

describe('place-holder', () => {
  it('can render a placeholder', async () => {
    const { container } = await render(
      join(__dirname, 'place-holder.twig'),
      {},
      namespaces,
    );

    expect(container).toMatchInlineSnapshot(`
      <div>
        

        <section
          class="place-holder"
        >
          
        
          <div
            class="place-holder__content"
          >
            
          Place holder
        
          </div>
          

        </section>
        

      </div>
    `);
  });
});
