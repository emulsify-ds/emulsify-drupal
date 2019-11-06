// Simple Drupal.behaviors usage for Storybook

window.Drupal = { behaviors: {} };

(function (Drupal) {
  Drupal.throwError = function (error) {
    setTimeout(function () {
      throw error;
    }, 0);
  };

  Drupal.attachBehaviors = function (context) {
    context = context || document;
    const behaviors = Drupal.behaviors;

    Object.keys(behaviors).forEach(function (i) {
      if (typeof behaviors[i].attach === 'function') {
        try {
          behaviors[i].attach(context);
        } catch (e) {
          Drupal.throwError(e);
        }
      }
    });
  };
})(Drupal);
