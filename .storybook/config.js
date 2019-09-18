import { configure } from "@storybook/react"
import { action } from "@storybook/addon-actions"

// GLOBAL CSS
import '../dist/styles.css';

// GLOBAL JS
import '../dist/js/main.bundle.js';

const Twig = require('twig')
const twigDrupal = require('twig-drupal-filters')
const twigBEM = require('bem-twig-extension');
const twigAddAttributes = require('add-attributes-twig-extension');

twigDrupal(Twig);
twigBEM(Twig);
twigAddAttributes(Twig);

 // automatically import all files ending in *.stories.js
const req = require.context("../components", true, /.stories.js$/)
function loadStories() {
  req.keys().forEach(filename => req(filename))
}

const context = require.context('../components', true, /\.twig$/)
context.keys().forEach(key => {
  var template = context(key);
  Twig.twig({
    id: key,
    data: template.tokens,
    allowInlineIncludes: true,
    rethrow: true
  });
});

// twigDrupal(Twig);

// twigAddAttributes(Twig);


 // Gatsby's Link overrides:
// Gatsby defines a global called ___loader to prevent its method calls from creating console errors you override it here
global.___loader = {
  enqueue: () => {},
  hovering: () => {},
}
// Gatsby internal mocking to prevent unnecessary errors in storybook testing environment
global.__PATH_PREFIX__ = ""
// This is to utilized to override the window.___navigate method Gatsby defines and uses to report what path a Link would be taking us to if it wasn't inside a storybook
window.___navigate = pathname => {
  action("NavigateTo:")(pathname)
}
configure(loadStories, module)
