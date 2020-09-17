import { join } from 'path';
import { render, Twig } from 'twig-testing-library';

import { setupTwig, namespaces } from '../../../../.storybook/setupTwig';

setupTwig(Twig);

describe('site-header', () => {
  it('can render a site header', async () => {
    const { container } = await render(
      join(__dirname, 'site-header.twig'),
      {},
      namespaces,
    );

    expect(container).toMatchInlineSnapshot(`
      <div>
        


        <header
          class="header"
        >
          
        
          <div
            class="header__inner"
          >
            
          
            <div
              class="header__primary"
            >
              
            
              <div
                class="header__branding"
              >
                
                        


                <a
                  class="logo-link"
                  href="/"
                >
                  
            


        

                  <img
                    alt="Logo"
                    class="logo__image"
                    src="logo.png"
                  />
                  
        
                </a>
                
                    
              </div>
              
            
              <div
                class="header__menu"
              >
                
                        


                <nav>
                  
        
                  <a
                    class="toggle-expand"
                    href="#"
                    id="toggle-expand"
                  >
                    
          
                    <span
                      class="toggle-expand__open"
                    >
                      
            






                      <svg
                        class="toggle-expand__icon"
                      >
                        
            
                        <use
                          xlink:href="icons.svg#menu"
                        />
                        

                      </svg>
                      
            
                      <span
                        class="toggle-expand__text"
                      >
                        Main Menu
                      </span>
                      
          
                    </span>
                    
          
                    <span
                      class="toggle-expand__close"
                    >
                      
            
                      <span
                        class="toggle-expand__text"
                      >
                        Close
                      </span>
                      
          
                    </span>
                    
        
                  </a>
                  
        
                  <div
                    class="main-nav"
                    id="main-nav"
                  >
                    
          




          

                  </div>
                  

                </nav>
                
                    
              </div>
              
          
            </div>
            
          
            <div
              class="header__secondary"
            >
              
                    

                
            </div>
            
        
          </div>
          

        </header>
        

      </div>
    `);
  });
});
