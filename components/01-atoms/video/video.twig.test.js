import { join } from 'path';
import { render, Twig } from 'twig-testing-library';

import loadYaml from '../../../util/loadYaml';
import { setupTwig } from '../../../.storybook/setupTwig';

setupTwig(Twig);

describe('video', () => {
  it('can render a video', async () => {
    const { container } = await render(
      join(__dirname, 'video.twig'),
      loadYaml(join(__dirname, 'video.yml')),
    );

    expect(container).toMatchInlineSnapshot(`
      <div>
        


        <div
          class="video"
        >
          
            
          <iframe
            allowfullscreen="allowfullscreen"
            frameborder="0"
            height="480"
            src="https://www.youtube.com/embed/YRnVnlhjOBs?autoplay=0&start=0"
            title="Emulsify Video"
            width="854"
          />
          
        
        </div>
        

      </div>
    `);
  });

  it('can render a full video', async () => {
    const { container } = await render(
      join(__dirname, 'video.twig'),
      loadYaml(join(__dirname, 'video-full.yml')),
    );

    expect(container).toMatchInlineSnapshot(`
      <div>
        


        <div
          class="video video--full"
        >
          
            
          <iframe
            allowfullscreen="allowfullscreen"
            frameborder="0"
            height="480"
            src="https://www.youtube.com/embed/YRnVnlhjOBs?autoplay=0&start=0"
            title="Emulsify Video"
            width="854"
          />
          
        
        </div>
        

      </div>
    `);
  });
});
