import { join } from 'path';
import { render, Twig } from 'twig-testing-library';

import loadYaml from '../../../../util/loadYaml';
import { setupTwig, namespaces } from '../../../../.storybook/setupTwig';

setupTwig(Twig);

describe('inline-menu', () => {
  it('can render an inline menu', async () => {
    const { container } = await render(
      join(__dirname, 'inline-menu.twig'),
      loadYaml(join(__dirname, 'inline-menu.yml')),
      namespaces,
    );

    expect(container).toMatchInlineSnapshot(`
      <div>
        




          
          
                            
          


        <ul
          class="inline-menu"
        >
          
                  


          <li
            class="inline-menu__item"
          >
            
            
                


            <a
              class="inline-menu__link"
              href="#"
            >
              
            Test
        
            </a>
            
                
          </li>
          
                


          <li
            class="inline-menu__item"
          >
            
            
                


            <a
              class="inline-menu__link"
              href="#"
            >
              
            Number 2
        
            </a>
            
                
          </li>
          
                


          <li
            class="inline-menu__item"
          >
            
            
                


            <a
              class="inline-menu__link"
              href="#"
            >
              
            Item Number 3
        
            </a>
            
                
          </li>
          
            
        </ul>
        
        

      </div>
    `);
  });
});
