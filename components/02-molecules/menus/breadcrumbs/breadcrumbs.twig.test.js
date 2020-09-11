import { join } from 'path';
import { render, Twig } from 'twig-testing-library';

import loadYaml from '../../../../util/loadYaml';
import { setupTwig } from '../../../../.storybook/setupTwig';

setupTwig(Twig);

describe('breadcrumbs', () => {
  it('can render a breadcrumb trail', async () => {
    const { container } = await render(
      join(__dirname, 'breadcrumbs.twig'),
      loadYaml(join(__dirname, 'breadcrumbs.yml')),
    );

    expect(container).toMatchInlineSnapshot(`
      <div>
        

        
        <nav
          aria-labelledby="system-breadcrumb"
          role="navigation"
        >
          
          
          <h2
            class="visually-hidden"
            id="system-breadcrumb"
          >
            Breadcrumb
          </h2>
          
          
          <ol
            class="breadcrumb"
          >
            
                
            <li
              class="breadcrumb__item"
            >
              
                        
              <a
                class="breadcrumb__link"
                href="#"
              >
                Home
              </a>
              
                    
            </li>
            
                
            <li
              class="breadcrumb__item"
            >
              
                        
              <a
                class="breadcrumb__link"
                href="#"
              >
                Parent Page
              </a>
              
                    
            </li>
            
                
            <li
              class="breadcrumb__item"
            >
              
                        Current Page
                    
            </li>
            
              
          </ol>
          
        
        </nav>
        

      </div>
    `);
  });
});
