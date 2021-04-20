const {
  TwingEnvironment,
  TwingFunction,
  TwingFilter,
  TwingLoaderRelativeFilesystem,
} = require('twing');
const twigBEM = require('bem-twig-extension');
const twigDrupal = require('twig-drupal-filters');
const twigAddAttributes = require('add-attributes-twig-extension');

const loader = new TwingLoaderRelativeFilesystem();
const environment = new TwingEnvironment(loader, {
  autoescape: false,
  debug: false,
});

const asPromise = (callback) => (...args) => {
  return Promise.resolve(callback(...args));
};

const TwigToTwing = {
  extendFunction(name, callback) {
    environment.addFunction(new TwingFunction(name, asPromise(callback)));
  },
  extendFilter(name, callback) {
    environment.addFilter(new TwingFilter(name, asPromise(callback)));
  },
};

twigBEM(TwigToTwing);
twigDrupal(TwigToTwing);
twigAddAttributes(TwigToTwing);

module.exports = environment;
