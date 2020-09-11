import { join } from 'path';
import { render, Twig } from 'twig-testing-library';

import loadYaml from '../../../../util/loadYaml';
import { setupTwig } from '../../../../.storybook/setupTwig';

setupTwig(Twig);

describe('text', () => {
  it('can render a paragraph', async () => {
    const { container } = await render(join(__dirname, '01-paragraph.twig'));
    expect(container).toMatchInlineSnapshot(`
      <div>
        


        <p
          class="paragraph"
        >
          
            
        
        </p>
        

      </div>
    `);
  });

  it('can render a blockquote', async () => {
    const { container } = await render(
      join(__dirname, '02-blockquote.twig'),
      loadYaml(join(__dirname, 'blockquote.yml')),
    );
    expect(container).toMatchInlineSnapshot(`
      <div>
        


        <blockquote
          class="blockquote"
        >
          
            Lorem ipsum dolor sit amet, consetetur sadipscing elitr, sed diam nonumy eirmod tempor invidunt ut labore et dolore magna aliquyam erat, sed diam voluptua. At vero eos et accusam et justo duo dolores et ea rebum. Stet clita kasd gubergren, no sea takimata sanctus est Lorem ipsum dolor sit amet. Lorem ipsum dolor sit amet, consetetur sadipscing elitr, sed diam nonumy eirmod tempor invidunt ut labore et dolore magna aliquyam erat, sed diam voluptua. At vero eos et accusam et justo duo dolores et ea rebum. Stet clita kasd gubergren, no sea takimata sanctus est Lorem ipsum dolor sit amet. Lorem ipsum dolor sit amet, consetetur sadipscing elitr, sed diam nonumy eirmod tempor invidunt ut labore et dolore magna aliquyam erat, sed diam voluptua. At vero eos et accusam et justo duo dolores et ea rebum. Stet clita kasd gubergren, no sea takimata sanctus est Lorem ipsum dolor sit amet.
        
        </blockquote>
        

      </div>
    `);
  });

  it('can render inline elements', async () => {
    const { container } = await render(
      join(__dirname, '03-inline-elements.twig'),
    );
    expect(container).toMatchSnapshot();
  });

  it('can render pre-formatted text', async () => {
    const { container } = await render(join(__dirname, '05-pre.twig'));
    expect(container).toMatchInlineSnapshot(`
      <div>
        

        <pre>
          P R E F O R M A T T E D T E X T
      ! " # $ % & ' ( ) * + , - . /
      0 1 2 3 4 5 6 7 8 9 : ; &lt; = &gt; ?
      @ A B C D E F G H I J K L M N O
      P Q R S T U V W X Y Z [ \\ ] ^ _
      \` a b c d e f g h i j k l m n o
      p q r s t u v w x y z { | } ~

        </pre>
        

      </div>
    `);
  });

  it('can render an hr', async () => {
    const { container } = await render(join(__dirname, '06-hr.twig'));
    expect(container).toMatchInlineSnapshot(`
      <div>
        <hr
          class="hr"
        />
        

      </div>
    `);
  });
});
