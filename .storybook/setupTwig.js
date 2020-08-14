import twigDrupal from 'twig-drupal-filters';
import twigBEM from 'bem-twig-extension';
import twigAddAttributes from 'add-attributes-twig-extension';

/**
 * Configures and extends a standard twig object.
 *
 * @param {Twig} twig - twig object that should be configured and extended.
 *
 * @returns {Twig} configured twig object.
 */
export default function setupTwig(twig) {
  twig.cache();
  twigDrupal(twig);
  twigBEM(twig);
  twigAddAttributes(twig);
  return twig;
}
