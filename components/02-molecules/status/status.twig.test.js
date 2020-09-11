import { join } from 'path';
import { render, Twig } from 'twig-testing-library';

import loadYaml from '../../../util/loadYaml';
import { setupTwig } from '../../../.storybook/setupTwig';

setupTwig(Twig);

describe('status', () => {
  it('can render a status', async () => {
    const { container } = await render(
      join(__dirname, 'status.twig'),
      loadYaml(join(__dirname, 'status.yml')),
    );

    expect(container).toMatchInlineSnapshot(`
      <div>
        
        
        <div
          [object=""
          aria-label="status"
          object]=""
        >
          
                        
          <h2
            class="visually-hidden"
          >
            status
          </h2>
          
                  
          <ul
            class="status__list"
          >
            
                        
            <li
              class="status status--status"
            >
              This is a status message
            </li>
            
                    
          </ul>
          
            
        </div>
        
        
        <div
          [object=""
          aria-label="warning"
          object]=""
        >
          
                        
          <h2
            class="visually-hidden"
          >
            warning
          </h2>
          
                  
          <ul
            class="status__list"
          >
            
                        
            <li
              class="status status--warning"
            >
              This is a warning message
            </li>
            
                    
          </ul>
          
            
        </div>
        
        
        <div
          [object=""
          aria-label="error"
          object]=""
        >
          
                
          <div
            role="alert"
          >
            
                        
            <h2
              class="visually-hidden"
            >
              error
            </h2>
            
                  
            <ul
              class="status__list"
            >
              
                        
              <li
                class="status status--error"
              >
                This is an error message
              </li>
              
                    
            </ul>
            
                
          </div>
          
            
        </div>
        

      </div>
    `);
  });
});
