import { join } from 'path';
import { render, Twig } from 'twig-testing-library';

import loadYaml from '../../../../util/loadYaml';
import { setupTwig, namespaces } from '../../../../.storybook/setupTwig';

setupTwig(Twig);

describe('radio', () => {
  it('can render a radio form item', async () => {
    const { container } = await render(
      join(__dirname, 'radio.twig'),
      loadYaml(join(__dirname, 'radio.yml')),
      namespaces,
    );

    expect(container).toMatchInlineSnapshot(`
      <div>
                  
        <li
          class="form-item--radio__item"
        >
          
        
          <label
            for="radio0"
          >
            
          
            <input
              checked="checked"
              class="radio"
              id="radio0"
              name="radio"
              type="radio"
            />
             Option 1
        
          </label>
          

        </li>
        
                
        <li
          class="form-item--radio__item"
        >
          
        
          <label
            for="radio1"
          >
            
          
            <input
              class="radio"
              id="radio1"
              name="radio"
              type="radio"
            />
             Option 2
        
          </label>
          

        </li>
        
                
        <li
          class="form-item--radio__item"
        >
          
        
          <label
            for="radio2"
          >
            
          
            <input
              class="radio"
              id="radio2"
              name="radio"
              type="radio"
            />
             Option 3
        
          </label>
          

        </li>
        
                
        <li
          class="form-item--radio__item"
        >
          
        
          <label
            for="radio3"
          >
            
          
            <input
              class="radio"
              id="radio3"
              name="radio"
              type="radio"
            />
             Option 4
        
          </label>
          

        </li>
        
          
      </div>
    `);
  });
});
