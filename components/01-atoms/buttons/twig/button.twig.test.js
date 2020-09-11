import { join } from 'path';
import { render, Twig } from 'twig-testing-library';

import loadYaml from '../../../../util/loadYaml';
import { setupTwig } from '../../../../.storybook/setupTwig';

setupTwig(Twig);

describe('Twig Button', () => {
  it('can render a button with the standard data', async () => {
    const { container } = await render(
      join(__dirname, 'button.twig'),
      loadYaml(join(__dirname, 'button.yml')),
    );
    expect(container).toMatchInlineSnapshot(`
      <div>
        




        <button
          aria-label="button"
          class="button"
        >
          
            Twig Button
        
        </button>
        

      </div>
    `);
  });

  it('can render a button with the alternative data', async () => {
    const { container } = await render(
      join(__dirname, 'button.twig'),
      loadYaml(join(__dirname, 'button-alt.yml')),
    );
    expect(container).toMatchInlineSnapshot(`
      <div>
        




        <button
          aria-label="button"
          class="button button--alt"
        >
          
            Alternative Button
        
        </button>
        

      </div>
    `);
  });
});
