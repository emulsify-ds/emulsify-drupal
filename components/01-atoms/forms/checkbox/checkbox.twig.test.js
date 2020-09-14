import { join } from 'path';
import { render, Twig } from 'twig-testing-library';

import loadYaml from '../../../../util/loadYaml';
import { namespaces, setupTwig } from '../../../../.storybook/setupTwig';

setupTwig(Twig);

describe('checkbox', () => {
  it('can render a checkbox form item', async () => {
    const { container } = await render(
      join(__dirname, 'checkbox.twig'),
      loadYaml(join(__dirname, 'checkbox.yml')),
      namespaces,
    );

    expect(container).toMatchInlineSnapshot(`
      <div>
              
        <li
          class="form-item--checkbox__item"
        >
          
        
          <label
            for="checkbox0"
          >
            
          
            <input
              checked="checked"
              id="checkbox0"
              name="checkbox"
              type="checkbox"
            />
             Option 1
        
          </label>
          

        </li>
        
            
        <li
          class="form-item--checkbox__item"
        >
          
        
          <label
            for="checkbox1"
          >
            
          
            <input
              id="checkbox1"
              name="checkbox"
              type="checkbox"
            />
             Option 2
        
          </label>
          

        </li>
        
            
        <li
          class="form-item--checkbox__item"
        >
          
        
          <label
            for="checkbox2"
          >
            
          
            <input
              id="checkbox2"
              name="checkbox"
              type="checkbox"
            />
             Option 3
        
          </label>
          

        </li>
        
            
        <li
          class="form-item--checkbox__item"
        >
          
        
          <label
            for="checkbox3"
          >
            
          
            <input
              id="checkbox3"
              name="checkbox"
              type="checkbox"
            />
             Option 4
        
          </label>
          

        </li>
        
        
      </div>
    `);
  });
});
