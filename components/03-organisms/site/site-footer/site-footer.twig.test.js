import { join } from 'path';
import { render, Twig } from 'twig-testing-library';

import { setupTwig, namespaces } from '../../../../.storybook/setupTwig';

setupTwig(Twig);

describe('site-footer', () => {
  it('can render a site footer', async () => {
    const { container } = await render(
      join(__dirname, 'site-footer.twig'),
      {},
      namespaces,
    );

    expect(container).toMatchInlineSnapshot(`
      <div>
        

        <footer
          class="footer"
        >
          
        
          <div
            class="footer__inner"
          >
            
          
            <div
              class="footer__social"
            >
              
                    


              <ul
                class="social-menu"
              >
                
              
              </ul>
              
                
            </div>
            
          
            <div
              class="footer__menu"
            >
              
                    




          
                
            </div>
            
        
          </div>
          

        </footer>
        

      </div>
    `);
  });
});
