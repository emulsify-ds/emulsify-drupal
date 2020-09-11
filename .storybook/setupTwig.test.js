jest.mock('path', () => ({
  resolve: (...paths) => `${paths[1]}${paths[2]}`,
}));
jest.mock('twig-drupal-filters', () => jest.fn());
jest.mock('bem-twig-extension', () => jest.fn());
jest.mock('add-attributes-twig-extension', () => jest.fn());

import Twig from 'twig';
import twigDrupal from 'twig-drupal-filters';
import twigBEM from 'bem-twig-extension';
import twigAddAttributes from 'add-attributes-twig-extension';

import { namespaces, setupTwig } from './setupTwig';

describe('setupTwig', () => {
  it('sets up a twig object with drupal, bem, and attribute decorations', () => {
    expect.assertions(3);
    setupTwig(Twig);
    expect(twigDrupal).toHaveBeenCalledWith(Twig);
    expect(twigBEM).toHaveBeenCalledWith(Twig);
    expect(twigAddAttributes).toHaveBeenCalledWith(Twig);
  });

  it('exports emulsifys namespaces', () => {
    expect(namespaces).toEqual({
      atoms: '../components/01-atoms',
      molecules: '../components/02-molecules',
      organisms: '../components/03-organisms',
      templates: '../components/04-templates',
    });
  });
});
