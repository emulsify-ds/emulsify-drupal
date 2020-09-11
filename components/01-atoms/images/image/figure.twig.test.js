import { join } from 'path';
import { render, Twig } from 'twig-testing-library';

import loadYaml from '../../../../util/loadYaml';
import { setupTwig, namespaces } from '../../../../.storybook/setupTwig';

setupTwig(Twig);

describe('figure', () => {
  it('can render a figure', async () => {
    const { container } = await render(
      join(__dirname, 'figure.twig'),
      loadYaml(join(__dirname, 'figure.yml')),
      namespaces,
    );

    expect(container).toMatchInlineSnapshot(`
      <div>
        

        <figure
          class="figure"
        >
          
            
          <a
            class="figure__link"
            href="#"
          >
            
                  


        

            <img
              alt="This is the alt text"
              class="figure__image"
              src="https://placeimg.com/1200/200/tech"
            />
            
                
          </a>
          
        
            
          <figcaption
            class="figure__caption"
          >
            
            This is an image caption.
          
          </figcaption>
          
        
        </figure>
        

      </div>
    `);
  });
});
